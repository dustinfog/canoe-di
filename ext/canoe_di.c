/*
 +----------------------------------------------------------------------+
 | PHP Version 5                                                        |
 +----------------------------------------------------------------------+
 | Copyright (c) 1997-2016 The PHP Group                                |
 +----------------------------------------------------------------------+
 | This source file is subject to version 3.01 of the PHP license,      |
 | that is bundled with this package in the file LICENSE, and is        |
 | available through the world-wide-web at the following url:           |
 | http://www.php.net/license/3_01.txt                                  |
 | If you did not receive a copy of the PHP license and are unable to   |
 | obtain it through the world-wide-web, please send a note to          |
 | license@php.net so we can mail you a copy immediately.               |
 +----------------------------------------------------------------------+
 | Author: 潘占东 <xodger@163.com>                                       |
 +----------------------------------------------------------------------+
 */

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

//#include "php_pcre.h"
#include "php.h"
#include "php_ini.h"
#include "ext/standard/info.h"
#include "ext/pcre/php_pcre.h"
#include "php_canoe_di.h"
#include "ext/spl/spl_exceptions.h"
#include "zend_exceptions.h"
#include "zend_closures.h"
#include "ext/standard/php_var.h"

#ifndef PREG_OFFSET_CAPTURE
# define PREG_OFFSET_CAPTURE                 (1<<8)
#endif

#define PROPERTY_REGEX "/\\*\\s*@property-?([^\\s]*)\\s+([^\\s]*)\\s*\\$([^\\s]*)/"

HashTable properties_cache;
HashTable definitions;
HashTable beans;

PHPAPI zend_class_entry *doc_property_ptr;
PHPAPI zend_class_entry *context_ptr;
PHPAPI zend_class_entry *di_trait_ptr;

int is_full_name(char *class_name) {
    return class_name[0] == '\\';
}

int get_namespace_length(const char *class_name, int len) {
	for(int i = len - 2; i >= 0; i --) {
		if (class_name[i] == '\\') {
			return i;
		}
	}

	return -1;
}

void integrate_class_name(const char *namespace, int namespace_length, char *type, int type_len, char *full_name, int *full_name_length)
{
	zend_class_entry **ce;

	if (is_full_name(type)) {
		strcpy(full_name, type);
		*full_name_length = type_len;

		return;
	}

	memcpy(full_name, namespace, namespace_length + 1);
	memcpy(full_name + namespace_length + 1, type, type_len);
	*full_name_length = namespace_length + type_len + 1;

	if (zend_lookup_class(full_name, *full_name_length, &ce TSRMLS_CC) == FAILURE) {
		strcpy(full_name, type);
		*full_name_length = type_len;
	}
}

zval *parse_property(const char *class_name, int class_name_len, zval *subpats, char **name, int *nameLen) {
	HashTable *ht = Z_ARRVAL_P(subpats);
	zval **match, **ppzval, *obj;


	MAKE_STD_ZVAL(obj);
	object_init_ex(obj, doc_property_ptr);

	if (zend_hash_index_find(ht, 1, (void **) &ppzval) == SUCCESS && zend_hash_index_find(Z_ARRVAL_PP(ppzval), 0, (void **) &match) == SUCCESS) {
		zend_update_property(doc_property_ptr, obj, "access", strlen("access"), *match);
	}

	if (zend_hash_index_find(ht, 2, (void **) &ppzval) == SUCCESS && zend_hash_index_find(Z_ARRVAL_PP(ppzval), 0, (void **) &match) == SUCCESS) {
		int namespaceLength = get_namespace_length(class_name, class_name_len);
		if (namespaceLength == -1) {
			zend_update_property(doc_property_ptr, obj, "type", strlen("type"), *match);
		} else {
			char *type = Z_STRVAL_PP(match);
			int type_len =Z_STRLEN_PP(match);
			int full_name_length = namespaceLength + type_len;
			char full_name[full_name_length + 1];

			integrate_class_name(class_name, namespaceLength, type, type_len, full_name, &full_name_length);

			zend_update_property_stringl(doc_property_ptr, obj, "type", strlen("type"), full_name, full_name_length TSRMLS_DC);
		}
	}

	if (zend_hash_index_find(ht, 3, (void **) &ppzval) == SUCCESS && zend_hash_index_find(Z_ARRVAL_PP(ppzval), 0, (void **) &match) == SUCCESS) {
		zend_update_property(doc_property_ptr, obj, "name", strlen("name"), *match);
		*name = Z_STRVAL_PP(match);
		*nameLen =Z_STRLEN_PP(match);
	}

	zval_delref_p(obj);
	return obj;
}

zval *parse_properties(zend_class_entry *ce) {
	const char *class_name = ce->name;
	int class_name_len = ce->name_length;
	int line_start = 0;
	pcre_cache_entry *pce;
	zval **cached_properties;
	zval *retval, *subpats;
	zval *return_value;
	zval *parent_properties;
	zend_class_entry *pzce = ce->parent;

	if(zend_hash_find(&properties_cache, class_name, class_name_len + 1, (void **)&cached_properties) == SUCCESS) {
		return *cached_properties;
	}

	const char *docComment = ce->info.user.doc_comment;
	int docLen = ce->info.user.doc_comment_len;

	if ((pce = pcre_get_compiled_regex_cache(PROPERTY_REGEX,
			strlen(PROPERTY_REGEX) TSRMLS_CC)) == NULL) {
		return NULL;
	}

	MAKE_STD_ZVAL(return_value);

	if (pzce != NULL && (parent_properties = parse_properties(pzce)) != NULL) {
		ZVAL_COPY_VALUE(return_value, parent_properties);
		zval_copy_ctor(return_value);
	} else {
		array_init(return_value);
	}


	MAKE_STD_ZVAL(retval);
	ALLOC_INIT_ZVAL(subpats);

	for (int i = 0; i < docLen; i++) {
		if (docComment[i] != '\n') {
			continue;
		}

		php_pcre_match_impl(pce, (char *) (docComment + line_start),
				i - line_start, retval, subpats, 0, 1, PREG_OFFSET_CAPTURE,
				0 TSRMLS_CC);

		if ((Z_LVAL_P(retval) > 0) && (Z_TYPE_P(subpats) == IS_ARRAY)) {
			char *name;
			int name_len;
			zval *obj = parse_property(class_name, class_name_len, subpats, &name, &name_len);
			if (add_assoc_zval_ex(return_value, name, name_len + 1, obj) == SUCCESS) {
				zval_addref_p(obj);
			}
			line_start = i + 1;
		}
	}

	zval_ptr_dtor(&subpats);
	FREE_ZVAL(retval);

	if (zend_hash_add(&properties_cache, class_name, class_name_len + 1, (void **)&return_value, sizeof(zval *), NULL) == SUCCESS) {
		zval_addref_p(return_value);
	}

	zval_delref_p(return_value);
	return return_value;
}

ZEND_METHOD(doc_property, __construct) {
}

ZEND_METHOD(doc_property, getName) {
	zval *val = zend_read_property(doc_property_ptr, getThis(), "name", strlen("name"), 0);
	MAKE_COPY_ZVAL(&val, return_value);
}

ZEND_METHOD(doc_property, getAccess) {
	zval *val = zend_read_property(doc_property_ptr, getThis(), "access", strlen("access"), 0);
	MAKE_COPY_ZVAL(&val, return_value);
}

ZEND_METHOD(doc_property, getType) {
	zval *val = zend_read_property(doc_property_ptr, getThis(), "type", strlen("type"), 0);
	MAKE_COPY_ZVAL(&val, return_value);
}

ZEND_METHOD(doc_property, parse) {
	char *class_name = NULL;
	int class_name_len;
	zend_class_entry **ce;
	zval *properties;

	if (!return_value_used) {
		return;
	}

	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &class_name, &class_name_len)
			== FAILURE) {
		return;
	}

	if (zend_lookup_class(class_name, class_name_len, &ce TSRMLS_CC) == FAILURE) {
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0 TSRMLS_CC,
				"Invalid class %s", class_name
			);
		return;
	}

	if((properties = parse_properties(*ce)) == NULL) {
		RETURN_NULL();
	} else {
		RETURN_ZVAL(properties, 1, 0);
	}
}

static zend_function_entry doc_property_methods[] = {
		ZEND_ME(doc_property, __construct, NULL, ZEND_ACC_PRIVATE)
		ZEND_ME(doc_property, parse, NULL, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
		ZEND_ME(doc_property, getName, NULL, ZEND_ACC_PUBLIC)
		ZEND_ME(doc_property, getAccess, NULL, ZEND_ACC_PUBLIC)
		ZEND_ME(doc_property, getType, NULL, ZEND_ACC_PUBLIC)
		{ NULL, NULL, NULL }
};

zval *get_bean(char *id, int id_len);
ZEND_RESULT_CODE create_from_class(char *class_name, int class_name_len, zval **instance);

ZEND_RESULT_CODE register_callable(zval *callback, char *id, char id_len) {
	zend_fcall_info fci;
	zend_fcall_info_cache fcc;

	if (id == NULL || id_len == 0) {
		zend_throw_exception(spl_ce_InvalidArgumentException, "Invalid argument, id cannot be null" TSRMLS_CC,
			0);
		return FAILURE;
	}

	if(zend_fcall_info_init(callback, 0, &fci, &fcc, NULL, NULL TSRMLS_CC) == FAILURE) {
		zend_throw_exception(spl_ce_InvalidArgumentException, "Invalid argument definition" TSRMLS_CC,
				0);
		return FAILURE;
	}

	if (fcc.function_handler->common.num_args != 0) {
		zend_throw_exception(spl_ce_InvalidArgumentException, "Invalid argument, definition callback cannot be with args" TSRMLS_CC,
						0);
		return FAILURE;
	}

	if (zend_hash_add(&definitions, id, id_len + 1, (void **) &callback, sizeof(zval *), NULL) == SUCCESS) {
		zval_addref_p(callback);
		return SUCCESS;
	}

	return FAILURE;
}

void auto_register_class(zend_class_entry *ce, zval *definition)
{
	char *class_name;
	int class_name_len;

	zend_class_entry *pce = ce;
	while (pce != NULL) {
		class_name = (char *)pce->name;
		class_name_len = pce->name_length;

		if (!zend_hash_exists(&definitions, class_name, class_name_len + 1)
				&& zend_hash_add(&definitions, class_name, class_name_len + 1, (void **) &definition, sizeof(zval *), NULL) == SUCCESS) {
			zval_addref_p(definition);;
		}

		pce = pce->parent;
	}

	for (zend_uint num_interfaces = 0; num_interfaces < ce->num_interfaces; num_interfaces++) {
		pce = ce->interfaces[num_interfaces];
		class_name = (char *)pce->name;
		class_name_len = pce->name_length;

		if (!zend_hash_exists(&definitions, class_name, class_name_len + 1)
				&& zend_hash_add(&definitions, class_name, class_name_len + 1, (void **) &definition, sizeof(zval *), NULL) == SUCCESS) {
			zval_addref_p(definition);
		}
	}
}

ZEND_RESULT_CODE register_class(zval *definition, char *id, char id_len) {
	zend_class_entry **ce;
	zend_class_entry **id_ce;

	char *class_name = Z_STRVAL_P(definition);
	int class_name_len = Z_STRLEN_P(definition);

	if (zend_lookup_class(class_name, class_name_len, &ce) == FAILURE) {
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0 TSRMLS_DC,  "class %s not exists!", class_name TSRMLS_CC);
		return FAILURE;
	}

	if (id != NULL && id_len != 0 && zend_lookup_class(id, id_len, &id_ce) == SUCCESS && !instanceof_function(*ce, *id_ce)) {
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0 TSRMLS_DC,  "class %s is not a subclass of %s!", class_name, id);
		return FAILURE;
	}

	auto_register_class(*ce, definition);

	if (id != NULL && id_len != 0 && zend_hash_add(&definitions, id, id_len + 1, (void **) &definition, sizeof(zval *), NULL) == SUCCESS) {
		zval_addref_p(definition);
	}

	return SUCCESS;
}

void auto_register_bean(zend_class_entry *ce, zval *value)
{
	char *class_name;
	int class_name_len;
	zend_class_entry *pce = ce;

	while(pce != NULL) {
		class_name = (char *)pce->name;
		class_name_len = pce->name_length;

		if (!zend_hash_exists(&beans, class_name, class_name_len + 1)
				&& zend_hash_add(&beans, class_name, class_name_len + 1, (void **)&value, sizeof(zval *), NULL) == SUCCESS ) {
			zval_addref_p(value);
		}
		pce = pce->parent;
	}

	for (zend_uint num_interfaces = 0; num_interfaces < ce->num_interfaces; num_interfaces++) {
		pce = ce->interfaces[num_interfaces];
		class_name = (char *)pce->name;
		class_name_len = pce->name_length;

		if (!zend_hash_exists(&beans, class_name, class_name_len + 1)
				&& zend_hash_add(&beans, class_name, class_name_len + 1, (void **) &value, sizeof(zval *), NULL) == SUCCESS) {
			zval_addref_p(value);
		}
	}
}

ZEND_RESULT_CODE register_bean(char *id, int id_len, zval *value)
{
	zend_class_entry **id_ce;
	zend_class_entry *ce;
	int is_object;

	if (id == NULL || id_len == 0) {
		zend_throw_exception(spl_ce_InvalidArgumentException, "Invalid argument, id cannot be null" TSRMLS_CC,
			0);
		return FAILURE;
	}

	is_object = Z_TYPE_P(value) == IS_OBJECT && HAS_CLASS_ENTRY(*value);
	if (is_object) {
		ce = Z_OBJCE_P(value);
	}

	if (zend_lookup_class(id, id_len, &id_ce) != FAILURE && is_object && !instanceof_function(ce, *id_ce)) {
		zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0 TSRMLS_CC,  "bean is not a instance of %s", id);
		return FAILURE;
	}

	if (zend_hash_update(&beans, id, id_len, (void **)&value, sizeof(zval *), NULL) == SUCCESS) {
		zval_addref_p(value);
	}

	if (is_object) {
		auto_register_bean(ce, value);
	}

	return SUCCESS;
}

zval *create_param(struct _zend_arg_info *arg_info)
{
	char *name = (char *)arg_info->name;
	zend_uint name_len = arg_info->name_len;
	char *class_name = (char *)arg_info->class_name;
	zend_uint class_name_len = arg_info->class_name_len;
	zend_class_entry **prop_class;
	zval *bean = get_bean(name, name_len);

	if (class_name == NULL
			|| class_name_len == 0
			|| zend_lookup_class(class_name, class_name_len, &prop_class) == FAILURE)
	{
		return bean;
	}

	if (bean == NULL
			|| Z_TYPE_P(bean) != IS_OBJECT
			|| !HAS_CLASS_ENTRY(*bean)
			|| !instanceof_function(Z_OBJCE_P(bean), *prop_class))
	{
		bean = get_bean(class_name, class_name_len);
	}

	return bean;
}

ZEND_RESULT_CODE create_from_class(char *class_name, int class_name_len, zval **instance)
{
    zend_class_entry **ce;
    zend_function *constructor;

    if (zend_lookup_class(class_name, class_name_len, &ce) == FAILURE) {
		return FAILURE;
    }

    MAKE_STD_ZVAL(*instance);
    object_init_ex(*instance, *ce);
    constructor = (*ce)->constructor;
    if (constructor) {
		int num_args = constructor->common.required_num_args;
		zval *params[num_args];
		zval **pparams[num_args];
		zend_fcall_info fci;
		zend_fcall_info_cache fcc;
		zval *retval_ptr = NULL;

		if (num_args > 0) {
			struct _zend_arg_info *arg_info = constructor->common.arg_info;

			for(int i = 0; i < num_args; i ++) {
				if ((params[i] = create_param(arg_info)) != NULL) {
					pparams[i] = &(params[i]);
					arg_info ++;
				} else {
					zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0, "cannot autowire param %s", arg_info->name);
					zval_dtor(*instance);
					return FAILURE;
				}
			}
		}

		fci.size = sizeof(fci);
		fci.function_table = EG(function_table);
		fci.function_name = NULL;
		fci.symbol_table = NULL;
		fci.object_ptr = *instance;
		fci.retval_ptr_ptr = &retval_ptr;
		fci.param_count = num_args;
		fci.params = pparams;
		fci.no_separation = 1;

		fcc.initialized = 1;
		fcc.function_handler = constructor;
		fcc.calling_scope = EG(scope);
		fcc.called_scope = *ce;
		fcc.object_ptr = *instance;

		if (zend_call_function(&fci, &fcc TSRMLS_CC) == FAILURE) {
			if (retval_ptr) {
				zval_ptr_dtor(&retval_ptr);
			}
			php_error_docref(NULL TSRMLS_CC, E_WARNING, "Invocation of %s's constructor failed", (*ce)->name);
			zval_dtor(*instance);
			return FAILURE;
		}

		if (retval_ptr) {
			zval_ptr_dtor(&retval_ptr);
		}
    }

	zval_delref_p(*instance);
	return SUCCESS;
}

ZEND_RESULT_CODE create_from_definition(zval *definition, zval **instance)
{
	if (zend_is_callable(definition, 0, NULL)) {
		zend_fcall_info fci;
		zend_fcall_info_cache fcc;
		char *error = NULL;
		if (zend_fcall_info_init(definition, 0, &fci, &fcc, NULL, &error TSRMLS_CC) == SUCCESS) {
			int ret;
			fci.retval_ptr_ptr = instance;
			ret = zend_call_function(&fci, &fcc TSRMLS_DC);
			if (error != NULL) {
				efree(error);
			}

			return ret;
		}

		zend_throw_exception(spl_ce_InvalidArgumentException,  "unexpected error", 0 TSRMLS_CC);
		return FAILURE;
	}

    return create_from_class(Z_STRVAL_P(definition), Z_STRLEN_P(definition), instance);
}

zval *get_bean(char *id, int id_len)
{
	zval **value = NULL;
	zval **definition = NULL;
	zval *ret = NULL;

	if (zend_hash_find(&beans, id, id_len + 1, (void **)&value) == SUCCESS) {
		return *value;
	}

	if (zend_hash_find(&definitions, id, id_len + 1, (void **)&definition) == SUCCESS) {
		if(create_from_definition(*definition, &ret) == SUCCESS && register_bean(id, id_len, ret) == SUCCESS) {
			return ret;
		}

		zend_throw_exception(spl_ce_RuntimeException, "Bad definition" TSRMLS_CC,
				0);
	    return NULL;
	}

	if (create_from_class(id, id_len, &ret) == SUCCESS && register_bean(id, id_len, ret) == SUCCESS) {
		return ret;
	}

	return NULL;
}

void register_definition(zval *definition, char *id, int id_len)
{
	if (zend_is_callable(definition, 0, NULL)) {
		register_callable(definition, id, id_len);
	} else if (Z_TYPE_P(definition) == IS_STRING) {
		register_class(definition, id, id_len);
	} else {
		zend_throw_exception(spl_ce_InvalidArgumentException, "Invalid definition!" TSRMLS_CC,
				0);
	}
}

PHP_METHOD(context, __construct) {

}

PHP_METHOD(context, registerDefinition) {
	zval *definition;
	char *id = NULL;
	int id_len = 0;

	if (zend_parse_parameters(ZEND_NUM_ARGS(), "z|s", &definition, &id, &id_len) == FAILURE) {
		zend_throw_exception(spl_ce_InvalidArgumentException, "Invalid arguments" TSRMLS_CC,
				0);
		return;
	}

	register_definition(definition, id, id_len);
}

PHP_METHOD(context, set) {
	char *id;
	int id_len;
	zval *value;

	if (zend_parse_parameters(ZEND_NUM_ARGS(), "sz", &id, &id_len, &value) == FAILURE) {
		zend_throw_exception(spl_ce_InvalidArgumentException, "Invalid arguments" TSRMLS_CC,
				0);
		return;
	}

	register_bean(id, id_len, value);
}

PHP_METHOD(context, get) {
	char *id = NULL;
	int id_len = 0;

	if (zend_parse_parameters(ZEND_NUM_ARGS(), "s", &id, &id_len) == FAILURE) {
		zend_throw_exception(spl_ce_InvalidArgumentException, "Invalid arguments" TSRMLS_CC,
				0);
		return;
	}

	zval *value = get_bean(id, id_len);
	if (value != NULL) {
		RETURN_ZVAL(value, 1, 0);
	} else {
		RETURN_NULL();
	}
}

PHP_METHOD(context, loadConfig) {
	zval *config;
	zval **definitions;
	zval **beans;
	HashTable *arrht;
	HashPosition pos;
	zval **entry;

	if (zend_parse_parameters(ZEND_NUM_ARGS(), "a", &config) == FAILURE) {
		zend_throw_exception(spl_ce_InvalidArgumentException, "Invalid arguments" TSRMLS_CC,
				0);
		return;
	}

	if (zend_hash_find(Z_ARRVAL_P(config), "definitions", strlen("definitions") + 1, (void **)&definitions) == SUCCESS) {
		if (Z_TYPE_PP(definitions) != IS_ARRAY) {
			zend_throw_exception(spl_ce_InvalidArgumentException, "Invalid definitions" TSRMLS_CC,
					0);
			return;
		}

		arrht = Z_ARRVAL_PP(definitions);

		for (zend_hash_internal_pointer_reset_ex(arrht, &pos);
			zend_hash_get_current_data_ex(arrht, (void **)&entry, &pos) == SUCCESS;
			zend_hash_move_forward_ex(arrht, &pos)
		) {
			char *id = NULL;
			uint id_len = 0;
			unsigned long index;
			zend_hash_get_current_key_ex(arrht, &id, &id_len, &index, 0, NULL);
			register_definition(*entry, id, id_len);
		}
	}

	if (zend_hash_find(Z_ARRVAL_P(config), "beans", strlen("beans") + 1, (void **)&beans) == SUCCESS) {
		if (Z_TYPE_PP(beans) != IS_ARRAY) {
			zend_throw_exception(spl_ce_InvalidArgumentException, "Invalid beans" TSRMLS_CC,
					0);
			return;
		}

		arrht = Z_ARRVAL_PP(beans);

		for (zend_hash_internal_pointer_reset_ex(arrht, &pos);
			zend_hash_get_current_data_ex(arrht, (void **)&entry, &pos) == SUCCESS;
			zend_hash_move_forward_ex(arrht, &pos)
		) {
			char *id = NULL;
			uint id_len = 0;
			unsigned long index;
			zend_hash_get_current_key_ex(arrht, &id, &id_len, &index, 0, NULL);
			register_bean(id, id_len, *entry);
		}
	}
}

static zend_function_entry context_methods[] = {
		ZEND_ME(context, __construct, NULL, ZEND_ACC_PRIVATE)
		ZEND_ME(context, registerDefinition, NULL, ZEND_ACC_STATIC|ZEND_ACC_PUBLIC)
		ZEND_ME(context, set, NULL, ZEND_ACC_STATIC|ZEND_ACC_PUBLIC)
		ZEND_ME(context, get, NULL, ZEND_ACC_STATIC|ZEND_ACC_PUBLIC)
		ZEND_ME(context, loadConfig, NULL, ZEND_ACC_STATIC|ZEND_ACC_PUBLIC)
		{ NULL, NULL, NULL }
};

ZEND_BEGIN_ARG_INFO(__get_arg_info, 0)
        ZEND_ARG_TYPE_INFO(0, name, IS_STRING, 0)
ZEND_END_ARG_INFO()

PHP_METHOD(di_trait, __get) {
	char *name = NULL;
	int name_len = 0;
	zend_class_entry *self;
	zend_class_entry **prop_class = NULL;
	zval *properties = NULL;
	zval **property;
	zval *bean;
	int from_class = 0;
	zval *type;
	char ***test;

	if (zend_parse_parameters(ZEND_NUM_ARGS(), "s", &name, &name_len) == FAILURE) {
		zend_throw_exception(spl_ce_InvalidArgumentException, "Invalid arguments" TSRMLS_CC,
				0);
		return;
	}

	self = zend_get_class_entry(getThis());

	if ((properties = parse_properties(self)) == NULL
			|| zend_hash_find(Z_ARRVAL_P(properties), name, name_len + 1, (void **)&property) == FAILURE) {
		RETURN_NULL();
	}

	bean = get_bean(name, name_len);

	if ((type = zend_read_property(doc_property_ptr, *property, "type", strlen("type"), 0)) != NULL
			&& zend_lookup_class(Z_STRVAL_P(type), Z_STRLEN_P(type), &prop_class) == SUCCESS
			&& (bean == NULL
					|| Z_TYPE_P(bean) != IS_OBJECT
					|| !HAS_CLASS_ENTRY(*bean)
					|| !instanceof_function(Z_OBJCE_P(bean), *prop_class)))
	{
		bean = get_bean((char *)(*prop_class)->name, (*prop_class)->name_length);
	}

	if (bean == NULL) {
		RETURN_NULL();
	} else {
		RETURN_ZVAL(bean, 1, 0);
	}
}

static zend_function_entry di_trait_methods[] = {
		ZEND_ME(di_trait, __get, __get_arg_info, ZEND_ACC_PUBLIC)
		{NULL, NULL, NULL}
};

PHP_MINIT_FUNCTION(canoe_di) {
	zend_class_entry canoe_entry;
	INIT_CLASS_ENTRY(canoe_entry, "Canoe\\Utils\\DocProperty", doc_property_methods);
	doc_property_ptr = zend_register_internal_class(&canoe_entry TSRMLS_CC);

    zend_declare_property_null(doc_property_ptr, "access", strlen("access"), ZEND_ACC_PRIVATE TSRMLS_CC);
    zend_declare_property_null(doc_property_ptr, "type", strlen("type"), ZEND_ACC_PRIVATE TSRMLS_CC);
    zend_declare_property_null(doc_property_ptr, "name", strlen("name"), ZEND_ACC_PRIVATE TSRMLS_CC);

    INIT_CLASS_ENTRY(canoe_entry, "Canoe\\DI\\Context", context_methods);
    context_ptr = zend_register_internal_class(&canoe_entry TSRMLS_CC);

    INIT_CLASS_ENTRY(canoe_entry, "Canoe\\DI\\DITrait", di_trait_methods);
    di_trait_ptr = zend_register_internal_class(&canoe_entry TSRMLS_CC);
    di_trait_ptr->ce_flags = ZEND_ACC_TRAIT;

	return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(canoe_di) {
	return SUCCESS;
}

PHP_RINIT_FUNCTION(canoe_di) {
	zend_hash_init(&properties_cache, 10, NULL, ZVAL_PTR_DTOR, 0);
	zend_hash_init(&definitions, 10, NULL, ZVAL_PTR_DTOR, 0);
	zend_hash_init(&beans, 10, NULL, ZVAL_PTR_DTOR, 0);
	return SUCCESS;
}

PHP_RSHUTDOWN_FUNCTION(canoe_di) {
	zend_hash_clean(&properties_cache);
	zend_hash_destroy(&properties_cache);
	zend_hash_clean(&definitions);
	zend_hash_destroy(&definitions);
	zend_hash_clean(&beans);
	zend_hash_destroy(&beans);
	return SUCCESS;
}

PHP_MINFO_FUNCTION(canoe_di) {
	php_info_print_table_start();
	php_info_print_table_header(2, "canoe_di support", "enabled");
	php_info_print_table_end();

	/* Remove comments if you have entries in php.ini
	 DISPLAY_INI_ENTRIES();
	 */
}

const zend_function_entry canoe_di_functions[] = {
PHP_FE_END };

zend_module_entry canoe_di_module_entry = {
		STANDARD_MODULE_HEADER, "canoe_di", canoe_di_functions,
		PHP_MINIT(canoe_di),
		PHP_MSHUTDOWN(canoe_di),
		PHP_RINIT(canoe_di),
		PHP_RSHUTDOWN(canoe_di),
		PHP_MINFO(canoe_di),
		PHP_CANOE_DI_VERSION,
		STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_CANOE_DI
ZEND_GET_MODULE(canoe_di)
#endif

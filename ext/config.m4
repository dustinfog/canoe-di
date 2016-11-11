dnl $Id$
dnl config.m4 for extension canoe_di

dnl Comments in this file start with the string 'dnl'.
dnl Remove where necessary. This file will not work
dnl without editing.

dnl If your extension references something external, use with:

dnl PHP_ARG_WITH(canoe_di, for canoe_di support,
dnl Make sure that the comment is aligned:
dnl [  --with-canoe_di             Include canoe_di support])

dnl Otherwise use enable:

PHP_ARG_ENABLE(canoe_di, whether to enable canoe_di support,
Make sure that the comment is aligned:
[  --enable-canoe_di           Enable canoe_di support])

if test "$PHP_CANOE_DI" != "no"; then
  dnl Write more examples of tests here...

  dnl # --with-canoe_di -> check with-path
  dnl SEARCH_PATH="/usr/local /usr"     # you might want to change this
  dnl SEARCH_FOR="/include/canoe_di.h"  # you most likely want to change this
  dnl if test -r $PHP_CANOE_DI/$SEARCH_FOR; then # path given as parameter
  dnl   CANOE_DI_DIR=$PHP_CANOE_DI
  dnl else # search default path list
  dnl   AC_MSG_CHECKING([for canoe_di files in default path])
  dnl   for i in $SEARCH_PATH ; do
  dnl     if test -r $i/$SEARCH_FOR; then
  dnl       CANOE_DI_DIR=$i
  dnl       AC_MSG_RESULT(found in $i)
  dnl     fi
  dnl   done
  dnl fi
  dnl
  dnl if test -z "$CANOE_DI_DIR"; then
  dnl   AC_MSG_RESULT([not found])
  dnl   AC_MSG_ERROR([Please reinstall the canoe_di distribution])
  dnl fi

  dnl # --with-canoe_di -> add include path
  dnl PHP_ADD_INCLUDE($CANOE_DI_DIR/include)

  dnl # --with-canoe_di -> check for lib and symbol presence
  dnl LIBNAME=canoe_di # you may want to change this
  dnl LIBSYMBOL=canoe_di # you most likely want to change this 

  dnl PHP_CHECK_LIBRARY($LIBNAME,$LIBSYMBOL,
  dnl [
  dnl   PHP_ADD_LIBRARY_WITH_PATH($LIBNAME, $CANOE_DI_DIR/$PHP_LIBDIR, CANOE_DI_SHARED_LIBADD)
  dnl   AC_DEFINE(HAVE_CANOE_DILIB,1,[ ])
  dnl ],[
  dnl   AC_MSG_ERROR([wrong canoe_di lib version or lib not found])
  dnl ],[
  dnl   -L$CANOE_DI_DIR/$PHP_LIBDIR -lm
  dnl ])
  dnl
  dnl PHP_SUBST(CANOE_DI_SHARED_LIBADD)

  PHP_NEW_EXTENSION(canoe_di, canoe_di.c, $ext_shared)
fi

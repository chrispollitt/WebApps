// this is a c wrapper for Foo_Bar php module

#define _GNU_SOURCE

#include <unistd.h>
#include <stdlib.h>
#include <stdio.h>
#include <libgen.h>
#include <string.h>

#include "Foo_Bar.h"

int foo(char *three, char *two, char *one) {
  char myfilename[] = MY_FILE_NAME;
  FILE *fp;
  char php[FOOBAR_STRLEN];
  char cmd[FOOBAR_STRLEN];

  sprintf(php, "%s/user_php/Foo_Bar_w.php", dirname(dirname( myfilename )));
  sprintf(cmd,"php-cli %s '%s'",php,one);
  // call script
#ifdef DEBUG
  fprintf(stderr, "DEBUG: cmd=%s\n", cmd);
#endif
  fp = popen(cmd, "r");
  if(fp) {
    fgets(three, FOOBAR_STRLEN-1, fp);
    fgets(two,   FOOBAR_STRLEN-1, fp);
    three[strlen(three)-1] = '\0';
    two[strlen(two)-1] = '\0';
    pclose(fp);
  } else {
    sprintf(three, "failed");
    sprintf(two, "failed");
    fprintf(stderr, "foo() error");
  }
  return(1);
}

int bar(char *three, char *two, char *one) {
  char myfilename[] = MY_FILE_NAME;
  char php[FOOBAR_STRLEN];
  char cmd[FOOBAR_STRLEN];
  sprintf(php, "%s/user_php/Foo_Bar_w.php", dirname(dirname( myfilename )));
  sprintf(cmd, "php-cli %s '%s' '%s' '%s'", php,three,two,one);
  // call script
#ifdef DEBUG
  fprintf(stderr, "DEBUG: cmd=%s\n", cmd);
#endif
  return(system(cmd));
}

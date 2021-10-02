// test.c

#include <stdio.h>
#include <string.h>

#include "Foo_Bar.h"

int main(int argc, char **argv) {
  char three[FOOBAR_STRLEN] = "user";
  char two[FOOBAR_STRLEN]   = "pass";
  char one[FOOBAR_STRLEN]   = "host";

  // set
  bar(three, two, one);
  
  // get
  memset(three, 0, FOOBAR_STRLEN);
  memset(two, 0, FOOBAR_STRLEN);
  printf("three='%s' two='%s'\n", three, two);
  foo(three, two, one);
  printf("three='%s' two='%s'\n", three, two);
  return(0);
}

all: check

clean:
	-rm -f *~ *.o *.exe *.stackdump

check: check.exe
	./check

check.exe : check.o Foo_Bar.o
	gcc -o $@ $^

%.exe : %.o
	gcc -o $@ $<
	
%.o : %.c
	gcc -c -g -Wall $(CFLAGS) -DMY_FILE_NAME="\"$(PWD)/$<\"" $<

.SUFFIX:

.PHONY: all check clean

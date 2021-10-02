	.file	"Foo_Bar.c"
 # GNU C11 (GCC) version 5.4.0 (i686-pc-cygwin)
 #	compiled by GNU C version 5.4.0, GMP version 6.1.0, MPFR version 3.1.4, MPC version 1.0.3
 # warning: GMP header version 6.1.0 differs from library version 6.1.2.
 # warning: MPFR header version 3.1.4 differs from library version 3.1.5.
 # GGC heuristics: --param ggc-min-expand=100 --param ggc-min-heapsize=131072
 # options passed:  -Dunix
 # -idirafter /usr/lib/gcc/i686-pc-cygwin/5.4.0/../../../../include/w32api
 # -idirafter /usr/lib/gcc/i686-pc-cygwin/5.4.0/../../../../i686-pc-cygwin/lib/../../include/w32api
 # -D MY_FILE_NAME="/home/Chris/user_c/Foo_Bar.c" Foo_Bar.c -mtune=generic
 # -march=i686 -Og -Wall -fverbose-asm
 # options enabled:  -faggressive-loop-optimizations
 # -fasynchronous-unwind-tables -fauto-inc-dec -fchkp-check-incomplete-type
 # -fchkp-check-read -fchkp-check-write -fchkp-instrument-calls
 # -fchkp-narrow-bounds -fchkp-optimize -fchkp-store-bounds
 # -fchkp-use-static-bounds -fchkp-use-static-const-bounds
 # -fchkp-use-wrappers -fcombine-stack-adjustments -fcommon -fcompare-elim
 # -fcprop-registers -fdefer-pop -fdelete-null-pointer-checks
 # -fdwarf2-cfi-asm -fearly-inlining -feliminate-unused-debug-types
 # -fforward-propagate -ffunction-cse -fgcse-lm -fgnu-runtime -fgnu-unique
 # -fguess-branch-probability -fident -finline -finline-atomics
 # -fipa-profile -fipa-pure-const -fipa-reference -fira-hoist-pressure
 # -fira-share-save-slots -fira-share-spill-slots -fivopts
 # -fkeep-inline-dllexport -fkeep-static-consts -fleading-underscore
 # -flifetime-dse -flto-odr-type-merging -fmath-errno -fmerge-constants
 # -fmerge-debug-strings -fomit-frame-pointer -fpeephole
 # -fprefetch-loop-arrays -freg-struct-return
 # -fsched-critical-path-heuristic -fsched-dep-count-heuristic
 # -fsched-group-heuristic -fsched-interblock -fsched-last-insn-heuristic
 # -fsched-rank-heuristic -fsched-spec -fsched-spec-insn-heuristic
 # -fsched-stalled-insns-dep -fschedule-fusion -fsemantic-interposition
 # -fset-stack-executable -fshow-column -fshrink-wrap -fsigned-zeros
 # -fsplit-ivs-in-unroller -fsplit-wide-types -fstdarg-opt
 # -fstrict-volatile-bitfields -fsync-libcalls -ftoplevel-reorder
 # -ftrapping-math -ftree-ccp -ftree-ch -ftree-coalesce-vars
 # -ftree-copy-prop -ftree-copyrename -ftree-cselim -ftree-dce
 # -ftree-dominator-opts -ftree-dse -ftree-forwprop -ftree-fre
 # -ftree-loop-if-convert -ftree-loop-im -ftree-loop-ivcanon
 # -ftree-loop-optimize -ftree-parallelize-loops= -ftree-phiprop
 # -ftree-reassoc -ftree-scev-cprop -ftree-sink -ftree-slsr -ftree-ter
 # -funit-at-a-time -funwind-tables -fverbose-asm -fzero-initialized-in-bss
 # -m32 -m80387 -m96bit-long-double -maccumulate-outgoing-args
 # -malign-double -malign-stringops -mavx256-split-unaligned-load
 # -mavx256-split-unaligned-store -mfancy-math-387 -mfp-ret-in-387
 # -mieee-fp -mlong-double-80 -mno-red-zone -mno-sse4 -mpush-args -msahf
 # -mstack-arg-probe -mvzeroupper

	.section .rdata,"dr"
LC0:
	.ascii "%s/user_php/Foo_Bar_w.php\0"
LC1:
	.ascii "php-cli %s '%s'\0"
LC2:
	.ascii "r\0"
LC3:
	.ascii "foo() error\0"
	.text
	.globl	_foo
	.def	_foo;	.scl	2;	.type	32;	.endef
_foo:
LFB10:
	.cfi_startproc
	pushl	%ebp	 #
	.cfi_def_cfa_offset 8
	.cfi_offset 5, -8
	pushl	%edi	 #
	.cfi_def_cfa_offset 12
	.cfi_offset 7, -12
	pushl	%esi	 #
	.cfi_def_cfa_offset 16
	.cfi_offset 6, -16
	pushl	%ebx	 #
	.cfi_def_cfa_offset 20
	.cfi_offset 3, -20
	subl	$220, %esp	 #,
	.cfi_def_cfa_offset 240
	movl	240(%esp), %esi	 # three, three
	movl	244(%esp), %ebp	 # two, two
	movl	$1836017711, 179(%esp)	 #, myfilename
	movl	$1749233509, 183(%esp)	 #, myfilename
	movl	$796092786, 187(%esp)	 #, myfilename
	movl	$1919251317, 191(%esp)	 #, myfilename
	movl	$1177510751, 195(%esp)	 #, myfilename
	movl	$1113550703, 199(%esp)	 #, myfilename
	movl	$1663988321, 203(%esp)	 #, myfilename
	movb	$0, 207(%esp)	 #, myfilename
	leal	179(%esp), %eax	 #, tmp102
	movl	%eax, (%esp)	 # tmp102,
	call	_dirname	 #
	movl	%eax, (%esp)	 # D.3764,
	call	_dirname	 #
	movl	%eax, 8(%esp)	 # D.3764,
	movl	$LC0, 4(%esp)	 #,
	leal	99(%esp), %ebx	 #, tmp103
	movl	%ebx, (%esp)	 # tmp103,
	call	_sprintf	 #
	movl	248(%esp), %eax	 # one, one
	movl	%eax, 12(%esp)	 # one,
	movl	%ebx, 8(%esp)	 # tmp103,
	movl	$LC1, 4(%esp)	 #,
	leal	19(%esp), %ebx	 #, tmp105
	movl	%ebx, (%esp)	 # tmp105,
	call	_sprintf	 #
	movl	$LC2, 4(%esp)	 #,
	movl	%ebx, (%esp)	 # tmp105,
	call	_popen	 #
	testl	%eax, %eax	 # fp
	je	L2	 #,
	movl	%eax, %ebx	 #, fp
	movl	%eax, 8(%esp)	 # fp,
	movl	$79, 4(%esp)	 #,
	movl	%esi, (%esp)	 # three,
	call	_fgets	 #
	movl	%ebx, 8(%esp)	 # fp,
	movl	$79, 4(%esp)	 #,
	movl	%ebp, (%esp)	 # two,
	call	_fgets	 #
	movl	$-1, %edx	 #, tmp111
	movl	$0, %eax	 #, tmp112
	movl	%edx, %ecx	 # tmp111, tmp108
	movl	%esi, %edi	 # three, three
	repnz scasb
	notl	%ecx	 # tmp109
	movb	$0, -2(%esi,%ecx)	 #, *_19
	movl	%edx, %ecx	 # tmp111, tmp115
	movl	%ebp, %edi	 # two, two
	repnz scasb
	movl	%ecx, %eax	 # tmp115, tmp116
	notl	%eax	 # tmp116
	movb	$0, -2(%ebp,%eax)	 #, *_23
	movl	%ebx, (%esp)	 # fp,
	call	_pclose	 #
	jmp	L3	 #
L2:
	movl	$1818845542, (%esi)	 #, MEM[(void *)three_13(D)]
	movw	$25701, 4(%esi)	 #, MEM[(void *)three_13(D)]
	movb	$0, 6(%esi)	 #, MEM[(void *)three_13(D)]
	movl	$1818845542, 0(%ebp)	 #, MEM[(void *)two_15(D)]
	movw	$25701, 4(%ebp)	 #, MEM[(void *)two_15(D)]
	movb	$0, 6(%ebp)	 #, MEM[(void *)two_15(D)]
	call	___getreent	 #
	movl	12(%eax), %eax	 # _29->_stderr, D.3768
	movl	%eax, 12(%esp)	 # D.3768,
	movl	$11, 8(%esp)	 #,
	movl	$1, 4(%esp)	 #,
	movl	$LC3, (%esp)	 #,
	call	_fwrite	 #
L3:
	movl	$1, %eax	 #,
	addl	$220, %esp	 #,
	.cfi_def_cfa_offset 20
	popl	%ebx	 #
	.cfi_restore 3
	.cfi_def_cfa_offset 16
	popl	%esi	 #
	.cfi_restore 6
	.cfi_def_cfa_offset 12
	popl	%edi	 #
	.cfi_restore 7
	.cfi_def_cfa_offset 8
	popl	%ebp	 #
	.cfi_restore 5
	.cfi_def_cfa_offset 4
	ret
	.cfi_endproc
LFE10:
	.section .rdata,"dr"
LC4:
	.ascii "php-cli %s '%s' '%s' '%s'\0"
	.text
	.globl	_bar
	.def	_bar;	.scl	2;	.type	32;	.endef
_bar:
LFB11:
	.cfi_startproc
	pushl	%ebx	 #
	.cfi_def_cfa_offset 8
	.cfi_offset 3, -8
	subl	$232, %esp	 #,
	.cfi_def_cfa_offset 240
	movl	$1836017711, 195(%esp)	 #, myfilename
	movl	$1749233509, 199(%esp)	 #, myfilename
	movl	$796092786, 203(%esp)	 #, myfilename
	movl	$1919251317, 207(%esp)	 #, myfilename
	movl	$1177510751, 211(%esp)	 #, myfilename
	movl	$1113550703, 215(%esp)	 #, myfilename
	movl	$1663988321, 219(%esp)	 #, myfilename
	movb	$0, 223(%esp)	 #, myfilename
	leal	195(%esp), %eax	 #, tmp94
	movl	%eax, (%esp)	 # tmp94,
	call	_dirname	 #
	movl	%eax, (%esp)	 # D.3773,
	call	_dirname	 #
	movl	%eax, 8(%esp)	 # D.3773,
	movl	$LC0, 4(%esp)	 #,
	leal	115(%esp), %ebx	 #, tmp95
	movl	%ebx, (%esp)	 # tmp95,
	call	_sprintf	 #
	movl	248(%esp), %eax	 # one, one
	movl	%eax, 20(%esp)	 # one,
	movl	244(%esp), %eax	 # two, two
	movl	%eax, 16(%esp)	 # two,
	movl	240(%esp), %eax	 # three, three
	movl	%eax, 12(%esp)	 # three,
	movl	%ebx, 8(%esp)	 # tmp95,
	movl	$LC4, 4(%esp)	 #,
	leal	35(%esp), %ebx	 #, tmp97
	movl	%ebx, (%esp)	 # tmp97,
	call	_sprintf	 #
	movl	%ebx, (%esp)	 # tmp97,
	call	_system	 #
	addl	$232, %esp	 #,
	.cfi_def_cfa_offset 8
	popl	%ebx	 #
	.cfi_restore 3
	.cfi_def_cfa_offset 4
	ret
	.cfi_endproc
LFE11:
	.ident	"GCC: (GNU) 5.4.0"
	.def	_dirname;	.scl	2;	.type	32;	.endef
	.def	_sprintf;	.scl	2;	.type	32;	.endef
	.def	_popen;	.scl	2;	.type	32;	.endef
	.def	_fgets;	.scl	2;	.type	32;	.endef
	.def	_pclose;	.scl	2;	.type	32;	.endef
	.def	___getreent;	.scl	2;	.type	32;	.endef
	.def	_fwrite;	.scl	2;	.type	32;	.endef
	.def	_system;	.scl	2;	.type	32;	.endef

#!/bin/echo Must_be_used:
#
# Excel2CSV module
#

#####################################################
# set packagge name
package Excel2CSV;

# pragmas
use Modern::Perl '2018';
use experimental 'signatures';
use strict;
use warnings; no warnings  'experimental';

BEGIN {
  use Exporter   ();
  our ($VERSION, @ISA, @EXPORT, @EXPORT_OK, %EXPORT_TAGS);
  @ISA         = qw(Exporter);
  
  # set the version for version checking
  $VERSION     = 1.00;
  # named collections of vars & subs to be EXPORT_OK'ed as a group
  %EXPORT_TAGS = ( );
  # manually exported on user request
  @EXPORT_OK   = qw( );
  # auto exported
  @EXPORT      = qw(excel2csv csv2excel);
}
our @EXPORT_OK;

use Win32::OLE qw(in valof with CP_UTF8);
use Win32::OLE::Const 'Microsoft Excel';
use File::Basename;
use Text::CSV qw(csv);
use Cwd;
use CygPath;

our $Csv_quote_char  = '"';
our $Csv_escape_char = '"';
our $Csv_sep_char    = ",";
our $Csv_eol         = "\r\n";
our $Csv_ext         = ".csv";
our $Xls_ext         = '.xlsx';
our $Excel           = undef;
$Win32::OLE::CP      = CP_UTF8;

# Add quotes and/or escape chars as needed
sub csv_encode($line) {
  # change all non-printing chars to space
  $line =~ s/[\000-\037\177]/ /g;
  # if line has comma, double-quote, or space
  if($line =~ /[$Csv_sep_char$Csv_quote_char ]/) {
    # escape quotes
    $line =~ s/($Csv_quote_char)/$Csv_escape_char$1/g;
    # quote line
    $line = $Csv_quote_char . $line . $Csv_quote_char;
  }
  return $line;
}

# Convert XLSX -> CSV
sub excel2csv($file) {
  $file = win2cyg($file);
  my $dir;
  if($file =~ m,/,) { $dir = dirname($file) } else {$dir = getcwd() };
  $file = basename($file, $Xls_ext);
  my $Excel = Win32::OLE->new("Excel.Application") || die("fatal: " . Win32::OLE->LastError());
  $dir = cyg2win($dir);
  my $wb = $Excel->Workbooks->Open($dir . '\\' . $file . $Xls_ext) || die("fatal: " . Win32::OLE->LastError());
  my $sc = $wb->Sheets->Count;
  my $fh;
  my $value;
  
  for (my $i = 1; $i <= $sc; $i++) {
    my $sheet  = $wb->Sheets->Item($i);
    my $row = 1;
    my $col = 1;
    # Get Record size
    while(length( $sheet->Cells->Item($row, $col)->Value2)) {
      $col++;
    }
    my $max_col = $col -1;
    # Open file
    my $name = $file . $i . $Csv_ext;
    rename($name, $name . "~") if(-f $name);
    open($fh, '>:encoding(UTF-8)', $name);
    # Get Records
    $col = 1;
    while(length( $sheet->Cells->Item($row, $col)->Value2) ) {
      for (; $col <= $max_col; $col++) {
        $value = $sheet->Cells->Item($row, $col)->Value2 || "";
        print $fh csv_encode($value);
        print $fh $Csv_sep_char unless($col == $max_col);
      }
      print $fh $Csv_eol;
      $col = 1;
      $row++;
    }
    # Close file
    close($fh);
  }
  $Excel->Workbooks->Close();
  undef $Excel;
}

# Convert CSV -> XLSX
sub csv2excel($file) {
  $file = win2cyg($file);
  my $dir;
  if($file =~ m,/,) { $dir = dirname($file) } else {$dir = getcwd() };
  $file = basename($file, $Csv_ext);
  my $Excel = Win32::OLE->new("Excel.Application") || die("fatal: " . Win32::OLE->LastError());
  my $name = $dir . '/' . $file . $Xls_ext;
  rename($name, $name . "~") if(-f $name);
  my $wb = $Excel->Workbooks->Add() || die("fatal: " . Win32::OLE->LastError());
  my $tc = scalar( () = glob("$file*$Csv_ext") ); # table count
  my $value;
  
  my $sheet  = $wb->Sheets->Item(1);
  # Loop over tables
  for (my $i = 1; $i <= $tc; $i++) {
    my $sc = $wb->Sheets->Count;
    $wb->Sheets->Add( undef, $sheet ) if($sc < $i);
    $sheet  = $wb->Sheets->Item($i);
    $sheet->{Name} = "MySheet$i";
    $sheet->Tab->{ColorIndex} = $i+3;
    my $row = 1;
    my $col = 1;
    # Open file
    my $recs = csv(in => $file . $i . $Csv_ext, encoding => "UTF-8");
    # Get Records
    $col = 1;
    # Loop over records
    for my $rec (@{$recs}) {
      # Loop over fields
      for my $value (@{$rec}) {
        $sheet->Cells->Item($row, $col)->{Value} = $value;
        $col++;
      }
      $row++;
      $col = 1;
    }
    # insert table
    my $head  = ${$recs}[0];
    my $cols  = chr($#{$head} + 65);
    my $rows  = $#{$recs} + 1;
    my $table = $sheet->ListObjects->Add(xlSrcRange, $sheet->Range('$A$1:$'.$cols.'$'.$rows), undef, xlYes);
    # Set table name
    $table->{Name} = "MyTable$i";
    # fix widths
    $sheet->Columns("A:$cols")->{HorizontalAlignment} = xlLeft;
    $sheet->Columns("A:$cols")->EntireColumn->AutoFit;
    $sheet->Columns("A:$cols")->{WrapText} = 1;
    $sheet->Rows("1:$rows")->{RowHeight} = 15;
    for my $col ("A"..$cols) {
      $sheet->Columns("$col:$col")->{ColumnWidth} = 25 
        if($sheet->Columns("$col:$col")->{ColumnWidth} > 25);
    }
  }
  $wb->Sheets->Item(1)->Select;
  $wb->Sheets->Item(1)->Cells->Item(1, 1)->Select;
  $wb->SaveAs(cyg2win($name));
  $Excel->Workbooks->Close();
  undef $Excel;
}

# Close Excal if still open
END {
  $Excel->Workbooks->Close() if(defined $Excel);
# system('taskkill.exe /im excel.exe /f 2> /dev/null');
  system('tasklist.exe /fi "imagename eq excel.exe"');
  1;
}

# Return success
1;

# End of code, start of comments
__END__

###########################################################

TO DO:
* name and order sheet tabs properly (where to store names?)
* get all data into xlsx
* make tables proper tables (sortable, filterable, colours, bold)

---------


--DELETE SHEET
Sheets("Sheet1").Select
ActiveWindow.SelectedSheets.Delete

--ADD SORTABLE TABLE
Sheets("Sheet1").Select
ActiveSheet.ListObjects.Add(xlSrcRange, Range("$A$1:$C$7"),           , xlYes                                              ).Name = "Table1"
.                       Add(SourceType, Source            , LinkSource, XlListObjectHasHeaders, Destination, TableStyleName)

--ADD SHEET
Sheets("Sheet1").Select
Sheets.Add After:=ActiveSheet
Sheets("Sheet1").Name = "New1"
.
sheet = ActiveWorkbook.Sheets.Add(After:=ActiveWorkbook.Worksheets(ActiveWorkbook.Worksheets.Count))
Sheets.Add(Before, After, Count, Type)

--STYLE SORTABLE TABLE
Sheets("Sheet1").Select
ActiveSheet.ListObjects("Table1").TableStyle = "TableStyleMedium9"

--SORT TABLE
ActiveWorkbook.Worksheets("Sheet3").ListObjects("Table1").Sort.SortFields.Clear
ActiveWorkbook.Worksheets("Sheet3").ListObjects("Table1").Sort.SortFields.Add2 _
    Key:=Range("Table1[[#All],[a]]"), SortOn:=xlSortOnValues, Order:= _
    xlAscending, DataOption:=xlSortNormal
With ActiveWorkbook.Worksheets("Sheet3").ListObjects("Table1").Sort
    .Header = xlYes
    .MatchCase = False
    .Orientation = xlTopToBottom
    .SortMethod = xlPinYin
    .Apply
End With

with(OBJECT,
  PROPERTYNAME1 => VALUE1,
  PROPERTYNAME2 => VALUE2,
  PROPERTYNAME3 => VALUE3,
  ...
);

----------------

$ex = Win32::OLE->new('Excel.Application') or die "oops\n";
$ex->Amethod("arg")->Bmethod->{'Property'} = "foo";
$ex->Cmethod(undef,undef,$Arg3);
$ex->Dmethod($RequiredArg1, {NamedArg1 => $Value1, NamedArg2 => $Value2});

$wd = Win32::OLE->GetObject("D:\\Data\\Message.doc");

$xl = Win32::OLE->GetActiveObject("Excel.Application");

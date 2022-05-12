# Extending Files

Here you will find information on how you can tune the behaviour of certain parts
of the library.

## Disabling file existance checks in the FileManager during file loading

Although in the majority of cases you will want the `FileManager` to check if
the loaded files actually exist in their defined filesystems, there may be cases
where that is unnecessary. For example: generating a financial report using entities
that also have invoice scans that are irrelevant to the report itself. In this
case the file checks not only slow down the process significantly, but can break
it altogether if for any reason a file has gone missing.

In order to disable these checks, you can use the `FSi\Component\Files\FileManagerConfigurator\FileExistanceChecksConfigurator`
interface. Just check if the implementation of the `FileManager` that you are using
is implementing it and you toggle the aforementioned behaviour. By default it should
be turned on.

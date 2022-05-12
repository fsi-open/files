# FSi/Files

A component for handling file upload and storage. It streamlines the process of
uploading a file from different sources (API call, HTML form, local filesystem)
to a storage endpoint - SQL/NoSQL database, remote or local filesystem. This is
done through entity objects, that contain two fields per a `single persisted file`
- an object with the file itself and a path that is used for storing it. The entity
does not need to be tied to an ORM/ODM, but it can be used in tandem with them.

## Table of contents

- [Introduction](doc/introduction.md)
- [Usage](doc/usage.md)
- [Extensions](doc/extensions.md)

# Log of changes for Orders Admin module

## 1.0.0

* First initial release

## 1.0.1

* Fix error when using default location on a contact.

## 1.0.2

* Add additional extension hooks to model classes

## 1.0.3

* Clean up permissions and add can functions to notes

## 1.0.4

* Ensure associations are removed from the DB on contact deletion

## 1.0.5

* Switch bulk editing dependency 

## 1.0.6

* Add ability to bulk assign tags to contacts
* Add bulk editing fields to tags and lists 

## 1.1.0

* Update to work with latest bulk manager

## 1.1.1

* Add autocomplete fields for searchform in ContactAdmin
* Some minor code cleanup

## 1.2.0

* Allow associating a `Contact` to a `Member`
* Remove `Salutation` and `Middlename` from a `Contact`
* Add versioning support to a `Contact` and `ContactLocation`

## 1.2.1

* Automatically add region selection field if GeoLocations module is installed
* Hide Version field

## 1.2.2

* Switch top using `ModelAdminPlus`
* Add extension hook to required fields
<?php

namespace SilverCommerce\ContactAdmin\Import;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\CsvBulkLoader;
use SilverCommerce\ContactAdmin\Helpers\ContactHelper;
use SilverCommerce\ContactAdmin\Model\Contact;
use SilverCommerce\ContactAdmin\Model\ContactList;
use SilverCommerce\ContactAdmin\Model\ContactLocation;
use SilverCommerce\ContactAdmin\Model\ContactTag;

/**
 * Allow slightly more complex product imports from a CSV file
 *
 * @author i-lateral (http://www.i-lateral.com)
 * @package catalogue
 */
class ContactCSVBulkLoader extends CsvBulkLoader
{
    const ADDRESS_PREFIX = 'Address';

    public $columnMap = [
        "TagsList"              => '->importTagsList',
        "ListsList"              => '->importTagsList',
        "CreateMember"              => '->createMemberObject',
    ];

    public $duplicateChecks = [
        'ID'        => 'ID',
        'StockID'   => 'StockID'
    ];

    /**
     * Generate the selected relation from the provided array of values
     *
     * @param DataObject $object   The current object being imported
     * @param string $relation The name of the relation (eg Images)
     * @param array  $list     The list of values
     * @param string $class    The source class of the relation (eg SilverStripe\Assets\Image)
     * @param string $column   The name of the column to search for existing records
     * @param string $create   Create a new object if none found
     *
     * @return void
     */
    protected static function createRelationFromList(
        $object,
        $relation,
        $list,
        $class,
        $column,
        $create = false
    ) {
        if ($object->hasMethod($relation)) {
            $object->$relation()->removeAll();

            foreach ($list as $name) {
                $name = trim($name);

                if (!empty($name)) {
                    $obj = $class::get()->find($column, $name);

                    if (empty($obj) && $create) {
                        $obj = $class::create();
                        $obj->$column = $name;
                        $obj->write();
                    }

                    if (!empty($obj)) {
                        $object->$relation()->add($obj);
                    }
                }
            }
        }
    }

    /**
     * Collect the address data from the provided record array and return an
     * array of addresses
     *
     * @param array $record
     *
     * @return array
     */
    protected function collateAddressData(array $record)
    {
        $addresses = [];

        // Loop through all fields and find/compile addresses
        // denoted by a 'AddressX_XX' column
        foreach ($record as $key => $value) {
            if (strpos($key, self::ADDRESS_PREFIX) !== false) {
                $field_data = explode('_', $key);
                $pos = (int)substr($field_data[0], strlen(self::ADDRESS_PREFIX));

                if (!isset($addresses[$pos])) {
                    $addresses[$pos] = [];
                }

                $addresses[$pos][$field_data[1]] = $value;
            }
        }

        return $addresses;
    }

    /**
     * Either find (or create a new) contact location for the provided contact, in the defined position
     *
     * @param Contact $contact
     * @param array $address_data
     * @param int $pos
     *
     * @return ContactLocation  
     */
    protected function findOrMakeLocation(Contact $contact, array $address_data, int $pos)
    {
        $location = $contact
            ->Locations()
            ->offsetGet($pos);

        if (empty($location)) {
            $location = ContactLocation::create();
            $location->ContactID = $contact->ID;
        }

        $location->update($address_data);
        $location->write();

        return $location;
    }

    /**
     * Overwrite processing of individual record so we can collect and process location
     * data to generate locations
     *
     * @param array $record
     * @param array $columnMap
     * @param BulkLoader_Result $results
     * @param boolean $preview
     *
     * @return int
     */
    public function processRecord($record, $columnMap, &$results, $preview = false)
    {
        $this->extend("onBeforeProcess", $record, $columnMap, $results, $preview);

        $objID = parent::processRecord($record, $columnMap, $results, $preview);
        $object = DataObject::get_by_id(Contact::class, $objID);

        if (!empty($object) && $object instanceof Contact) {
            $addresses = $this->collateAddressData($record);
            $i = 0;

            foreach ($addresses as $address_data) {
                if (empty($address_data['Address1']) && empty($address_data['PostCode'])) {
                    continue;
                }

                $this->findOrMakeLocation($object, $address_data, $i);
                $i++;
            }
        }

        $this->extend("onAfterProcess", $object, $record, $columnMap, $results, $preview);

        if (!empty($object)) {
            $object->destroy();
            unset($object);
        }

        // Reset default object class
        if (!empty($curr_class)) {
            $this->objectClass = $curr_class;
        }

        return $objID;
    }

    public static function importTagsList(&$obj, $val, $record)
    {
        if (!$obj->exists()) {
            $obj->write();
        }

        self::createRelationFromList(
            $obj,
            'Tags',
            explode(",", $val),
            ContactTag::class,
            'Title',
            true
        );
    }

    public static function importListsList(&$obj, $val, $record)
    {
        if (!$obj->exists()) {
            $obj->write();
        }

        self::createRelationFromList(
            $obj,
            'Lists',
            explode(",", $val),
            ContactList::class,
            'Title',
            true
        );
    }

    /**
     * Generate a user account for this contact
     *
     * @return null
     */
    public static function createMemberObject(&$obj, $val, $record)
    {
        if (!$obj->exists()) {
            $obj->write();
        }

        $helper = ContactHelper::create()
            ->setContact($obj);

        $member = $helper->findOrMakeMember();
        $helper->setMember($member);
        $helper->linkMemberToGroups();
    }
}

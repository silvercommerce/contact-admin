<?php

namespace SilverCommerce\ContactAdmin\Helpers;

use DateTime;
use LogicException;
use SilverStripe\Security\Member;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverCommerce\ContactAdmin\Model\Contact;
use SilverStripe\Security\Group;

class ContactHelper
{
    use Injectable, Configurable;

    /**
     * The field that is shared between Members and Contacts.
     *
     * Defaults to "Email"
     *
     * @var string
     * @config
     */
    private static $common_field = "Email";

    /**
     * Fields that can be synced between members and contacts
     *
     * @var array
     */
    private static $sync_fields = [
        "FirstName",
        "Surname",
        "Company",
        "Phone",
        "Mobile",
        "Email"
    ];

    /**
     * Automatically sync users and contacts on save and auto create contacts for existing users
     *
     * @var boolean
     */
    private static $auto_sync = true;

    /**
     * Add codes for default groups linked user accounts are added to
     *
     * @var array
     */
    private static $default_user_groups = [
        'contact_users' => 'Contact Users'
    ];

    /**
     * @var Contact
     */
    private $contact;

    /**
     * @var Member
     */
    private $member;

    /**
     * Create a member from the provided contact
     *
     * @return Member
     */
    public function findOrMakeMember()
    {
        $contact = $this->getContact();

        if (empty($contact)) {
            throw new LogicException('Must set a Contact');
        }

        // First off see if the member from the contact is available
        $member = $contact->Member();
        $link = false;

        // If no member assigned, try to find one with matching field
        if (empty($member) || !$member->exists()) {
            $common_field = $this->config()->common_field;
            $member = Member::get()->find($common_field, $contact->{$common_field});
            $link = true;
        }

        // Finally, if still no member found, then create a new one, link to the contact
        // and pass the relevent data
        if (empty($member)) {
            $member = Member::create();
            self::pushFields($contact, $member);
            $member->write();
            $link = true;
        }

        if ($link) {
            $contact->MemberID = $member->ID;
            $contact->write();
        }

        return $member;
    }

    /**
     * Find or create a Contact from the provided member
     *
     * @return Contact
     */
    public function findOrMakeContact()
    {
        $member = $this->getMember();

        if (empty($member)) {
            throw new LogicException('Must set a Member');
        }

        // First off see if the contact is available as relation
        $contact = $member->Contact();
        $link = false;

        // If no member assigned, try to find one with matching field
        if (empty($contact) || !$contact->exists()) {
            $common_field = $this->config()->common_field;
            $contact = Contact::get()->find($common_field, $member->{$common_field});
            $link = true;
        }

        // Finally, if still no member found, then create a new one, link to the contact
        // and pass the relevent data
        if (empty($contact)) {
            $contact = Contact::create();
            self::pushFields($member, $contact);
            $link = true;
        }

        if ($link) {
            $contact->MemberID = $member->ID;
            $contact->write();
        }

        return $contact;
    }

    /**
     * Update an associated member with the data from this contact
     *
     * @todo Currently sync is pretty basic (pushes data from one object to another). This could be more intilligent.
     *
     * @param bool $write Write the syncronised record
     *
     * @return void
     */
    public function syncContactAndMember(bool $write = true)
    {
        $member = $this->getMember();
        $contact = $this->getContact();
        $changes = [];

        if (empty($member) || empty($contact)) {
            throw new LogicException('Must set a Member AND a Contact');
        }

        // Find out which object just changed and sync in the correct direction
        $member_changed = $member->getChangedFields();
        $contact_changed = $contact->getChangedFields();

        if (count($contact_changed) > 0) {
            $changes = self::pushChangedFields($contact, $member);
            $obj_to_write = $contact;
        } elseif (count($member_changed) > 0) {
            $changes = self::pushChangedFields($member, $contact);
            $obj_to_write = $member;
        }

        if (count($changes) > 0) {
            $obj_to_write->write();
        }

        return $this;
    }

    /**
     * Push any fields relevent fields changed on the origin obvject, to the destination,
     * if the destination is different.
     *
     * @var DataObject $origin
     * @var DataObject $destination
     *
     * @return array
     */
    public static function pushChangedFields($origin, $destination)
    {
        $sync = self::config()->sync_fields;
        $changes = [];

        foreach ($origin->getChangedFields() as $field => $change) {
            if (in_array($field, $sync) && !empty($origin->$field) && $origin->$field != $destination->$field) {
                $destination->$field = $origin->$field;
                $changes[$field] = $origin->$field;
            }
        }

        return $changes;
    }

    /**
     * Push the field values from the origin object and the destination object
     * (if values do not match)
     *
     * Return a list of fields pushed
     *
     * @var DataObject $origin
     * @var DataObject $destination
     *
     * @return array
     */
    public static function pushFields($origin, $destination)
    {
        $sync = self::config()->sync_fields;
        $changes = [];

        foreach ($sync as $field) {
            if ($origin->$field != $destination->$field) {
                $destination->$field = $origin->$field;
                $changes[$field] = $origin->$field;
            }
        }

        return $changes;
    }

    /**
     * Link the set member to all the groups specified via config
     *
     * Return the number of groups added
     *
     * @return int
     */
    public function linkMemberToGroups()
    {
        $member = $this->getMember();
        $groups = $this->config()->get('default_user_groups');
        $count = 0;

        if (empty($member)) {
            throw new LogicException('Must set a Member');
        }

        if (empty($groups)) {
            return $count;
        }

        foreach (array_keys($groups) as $code) {
            $group = Group::get()->filter('Code', $code)->first();

            if (!empty($group)) {
                $member->Groups()->add($group);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get the value of contact. If not assigned directly, try to get from the member
     *
     * @return Contact
     */
    public function getContact()
    {
        $contact = $this->contact;

        if (empty($contact) && !empty($this->getMember())) {
            $contact = $this->getMember()->Contact();
        }

        return $contact;
    }

    /**
     * Set the value of contact
     *
     * @param Contact $contact
     *
     * @return self
     */
    public function setContact(Contact $contact)
    {
        $this->contact = $contact;
        return $this;
    }

    /**
     * Get the value of member. If not assigned directly, try to get from Contact
     *
     * @return Member
     */
    public function getMember()
    {
        $member = $this->member;

        if (empty($member) && !empty($this->getContact())) {
            $member = $this->getContact()->Member();
        }

        return $member;
    }

    /**
     * Set the value of member
     *
     * @param Member $member
     *
     * @return self
     */
    public function setMember(Member $member)
    {
        $this->member = $member;
        return $this;
    }
}

<?php

namespace SilverCommerce\ContactAdmin\Extensions;

use SilverStripe\i18n\i18n;
use SilverStripe\Forms\Form;
use SilverStripe\Core\Extension;
use SilverStripe\View\ArrayData;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\Security\Security;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Core\Injector\Injector;
use SilverCommerce\ContactAdmin\Model\ContactLocation;
use ilateral\SilverStripe\Users\Control\AccountController;

/**
 * Add extra fields to a user account (if the users module is
 * installed) to allow logged in users to see their invoices.
 *
 * @package orders
 */
class AccountControllerExtension extends Extension
{
    /**
     * Add extra URL endpoints
     *
     * @var array
     */
    private static $allowed_actions = [
        "addresses",
        "addaddress",
        "editaddress",
        "removeaddress",
        "AddressForm"
    ];

    /**
     * Display all addresses associated with the current user
     *
     * @return HTMLText
     */
    public function addresses()
    {
        $member = Security::getCurrentUser();

        $this
            ->owner
            ->customise([
                "Title" => _t(
                    "SilverCommerce\ContactAdmin.YourAddresses",
                    "Your Addresses"
                ),
                "MetaTitle" => _t(
                    "SilverCommerce\ContactAdmin.YourAddresses",
                    "Your Addresses"
                ),
                "Content" => $this->owner->renderWith(
                    "SilverCommerce\\ContactAdmin\\Includes\\Addresses",
                    ["Contact" => $member->Contact()]
                )
            ]);

        $this->owner->extend("updateAddresses");

        return $this
            ->owner
            ->renderWith([
                'AccountController_addresses',
                AccountController::class . '_addresses',
                'AccountController',
                AccountController::class,
                'Page'
            ]);
    }

    /**
     * Display all addresses associated with the current user
     *
     * @return HTMLText
     */
    public function addaddress()
    {
        $form = $this->AddressForm();
        $member = Security::getCurrentUser();

        $form
            ->Fields()
            ->dataFieldByName("ContactID")
            ->setValue($member->Contact()->ID);

        $this
            ->owner
            ->customise([
                "Title" => _t(
                    "SilverCommerce\ContactAdmin.AddAddress",
                    "Add Address"
                ),
                "MetaTitle" => _t(
                    "SilverCommerce\ContactAdmin.AddAddress",
                    "Add Address"
                ),
                "Form" => $form
            ]);

        $this->owner->extend("updateAddAddress");

        return $this
            ->owner
            ->renderWith([
                'AccountController_addaddress',
                AccountController::class . '_addaddress',
                'AccountController',
                AccountController::class,
                'Page'
            ]);
    }

    /**
     * Display all addresses associated with the current user
     *
     * @return HTMLText
     */
    public function editaddress()
    {
        $member = Security::getCurrentUser();
        $id = $this->owner->request->param("ID");
        $address = ContactLocation::get()->byID($id);

        if ($address && $address->canEdit($member)) {
            $form = $this->AddressForm();
            $form->loadDataFrom($address);
            $form
                ->Actions()
                ->dataFieldByName("action_doSaveAddress")
                ->setTitle(_t("SilverCommerce\ContactAdmin.Save", "Save"));

            $this
                ->owner
                ->customise([
                    "Title" => _t(
                        "SilverCommerce\ContactAdmin.EditAddress",
                        "Edit Address"
                    ),
                    "MenuTitle" => _t(
                        "SilverCommerce\ContactAdmin.EditAddress",
                        "Edit Address"
                    ),
                    "Form" => $form
                ]);

            $this->owner->extend("updateEditAddress");
            
            return $this
                ->owner
                ->renderWith([
                    'AccountController_editaddress',
                    AccountController::class . '_editaddress',
                    'AccountController',
                    AccountController::class,
                    'Page'
                ]);
        } else {
            return $this->owner->httpError(404);
        }
    }

    /**
     * Remove an addresses by the given ID (if allowed)
     *
     * @return HTMLText
     */
    public function removeaddress()
    {
        $member = Security::getCurrentUser();
        $id = $this->owner->request->param("ID");
        $address = ContactLocation::get()->byID($id);

        if ($address && $address->canDelete($member)) {
            $address->delete();
            $this
                ->owner
                ->customise([
                    "Title" => _t(
                        "SilverCommerce\ContactAdmin.AddressRemoved",
                        "Address Removed"
                    ),
                    "MenuTitle" => _t(
                        "SilverCommerce\ContactAdmin.AddressRemoved",
                        "Address Removed"
                    )
                ]);

            $this->owner->extend("updateEditAddress");

            return $this
                ->owner
                ->renderWith([
                    'AccountController_removeaddress',
                    AccountController::class . '_removeaddress',
                    'AccountController',
                    AccountController::class,
                    'Page'
                ]);
        } else {
            return $this->owner->httpError(404);
        }
    }

        /**
     * Form used for adding or editing addresses
     *
     * @return Form
     */
    public function AddressForm()
    {
        $location = Injector::inst()->get(ContactLocation::class);

        $fields = FieldList::create(
            HiddenField::create("ID")
        );

        $fields->merge($location->getFrontEndFields());

        $fields->replaceField(
            "Country",
            DropdownField::create(
                'Country',
                $location->fieldLabel("Country"),
                i18n::getData()->getCountries()
            )->setEmptyString("")
        );

        $fields->replaceField(
            "ContactID",
            HiddenField::create('ContactID')
        );

        $back_btn = '<a href="';
        $back_btn .= $this->owner->Link('addresses');
        $back_btn .= '" class="btn btn-link">';
        $back_btn .= _t('SilverCommerce\ContactAdmin.Cancel', 'Cancel');
        $back_btn .= '</a>';

        $form = Form::create(
            $this->owner,
            "AddressForm",
            $fields,
            FieldList::create(
                LiteralField::create(
                    'BackButton',
                    $back_btn
                ),
                FormAction::create(
                    'doSaveAddress',
                    _t('SilverCommerce\ContactAdmin.Add', 'Add')
                )->addExtraClass('btn btn-success')
            ),
            RequiredFields::create(
                [
                    'FirstName',
                    'Surname',
                    'Address1',
                    'City',
                    'PostCode',
                    'Country',
                ]
            )
        );

        $this->owner->extend("updateAddressForm", $form);

        return $form;
    }

    /**
     * Method responsible for saving (or adding) an address.
     * If the ID field is set, the method assums we are saving
     * an address.
     *
     * If the ID field is not set, we assume a new address is being
     * created.
     *
     */
    public function doSaveAddress($data, $form)
    {
        if (!$data["ID"]) {
            $address = ContactLocation::create();
        } else {
            $address = ContactLocation::get()->byID($data["ID"]);
        }

        if ($address) {
            $form->saveInto($address);
            $address->write();
            $form->sessionMessage(
                _t("SilverCommerce\ContactAdmin.AddressSaved", "Address Saved"),
                ValidationResult::TYPE_GOOD
            );
        } else {
            $form->sessionMessage(
                _t("SilverCommerce\ContactAdmin.Error", "There was an error"),
                ValidationResult::TYPE_ERROR
            );
        }
        return $this->owner->redirect($this->owner->Link("addresses"));
    }

    /**
     * Add commerce specific links to account menu
     *
     * @param ArrayList $menu
     */
    public function updateAccountMenu($menu)
    {
        $curr_action = $this
            ->owner
            ->getRequest()
            ->param("Action");

        $menu->add(ArrayData::create([
            "ID"    => 11,
            "Title" => _t('SilverCommerce\ContactAdmin.Addresses', 'Addresses'),
            "Link"  => $this->owner->Link("addresses"),
            "LinkingMode" => ($curr_action == "addresses") ? "current" : "link"
        ]));
    }
}

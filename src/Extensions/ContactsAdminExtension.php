<?php

namespace SilverCommerce\ContactAdmin\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\View\Requirements;

class ContactsAdminExtension extends Extension
{
    public function init()
    {
        Requirements::css('silvercommerce/contact-admin: client/dist/css/admin.css');
    }
}

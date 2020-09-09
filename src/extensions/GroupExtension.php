<?php

namespace SilverCommerce\ContactAdmin\Extensions;

use SilverStripe\Security\Group;
use SilverStripe\ORM\DataExtension;
use SilverCommerce\ContactAdmin\Helpers\ContactHelper;
use SilverStripe\ORM\DB;

/**
 * Scaffold Any Default User Groups
 *
 */
class GroupExtension extends DataExtension
{
    public function requireDefaultRecords()
    {
        $groups = ContactHelper::config()->get('default_user_groups');

        foreach ($groups as $code => $title) {
            if (!is_string($code) || !is_string($title)) {
                continue;
            }

            $group =  Group::create([
                'Code' => $code,
                'Title' => $title
            ]);
            $group->write();

            DB::alteration_message('Created group ' . $title, 'created');
        }
    }
}

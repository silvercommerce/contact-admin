<?php

namespace SilverCommerce\ContactAdmin\Extensions;

use SilverStripe\ORM\DB;
use SilverStripe\Core\Convert;
use SilverStripe\Security\Group;
use SilverStripe\ORM\DataExtension;
use SilverCommerce\ContactAdmin\Helpers\ContactHelper;

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

            $code = Convert::raw2url($code);

            $existing = Group::get()->find('Code', $code);

            if (!empty($existing)) {
                DB::alteration_message('Skipping existing group ' . $title, 'error');
                continue;
            }

            $group = Group::create();
            $group->Code = $code;
            $group->Title = $title;
            $group->write();

            DB::alteration_message('Created group ' . $title, 'created');
        }
    }
}

<?php

namespace SilverCommerce\CatalogueAdmin\Search;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\ORM\Search\SearchContext;

class ContactSearchContext extends SearchContext
{
    const FILTER_SHOW_FLAGGED = 'ShowFlagged';

    /**
     * Overwrite default search fields and update with date ranges and
     * a dropdown for status
     *
     * @return \SilverStripe\Forms\FieldList
     */
    public function getSearchFields()
    {
        $fields = parent::getSearchFields();

        $flagged = CheckboxField::create(
            self::FILTER_SHOW_FLAGGED,
            _t(__CLASS__ . ".ShowFlaggedOnly", 'Show flagged only')
        );

        if ($this->isFlaggedFilterSet()) {
            $flagged->setValue(true);
        }

        $fields->push($flagged);

        return $fields;
    }

    /**
     * Add additional search filter to list for date range
     *
     * @param array $searchParams
     * @param array|bool|string $sort
     * @param array|bool|string $limit
     * @return DataList
     * @throws Exception
     */
    public function getQuery($searchParams, $sort = false, $limit = false, $existingQuery = null)
    {
        $query = parent::getQuery($searchParams, $sort, $limit);

        if ($this->isFlaggedFilterSet()) {
            $query = $query->filter('Notes.Flag', true);
        }

        return $query;
    }

    /**
     * Are we currently only showing flagged contacts
     *
     * @return bool
     */
    public function isFlaggedFilterSet($searchParams = [])
    {
        if (count($searchParams) == 0) {
            $searchParams = $this->getSearchParams();
        }

        if (count($searchParams) == 0) {
            return false;
        }

        if (isset($searchParams[self::FILTER_SHOW_FLAGGED])) {
            return true;
        }

        return false;
    }
}

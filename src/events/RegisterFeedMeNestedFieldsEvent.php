<?php

namespace craft\feedme\events;

use yii\base\Event;

class RegisterFeedMeNestedFieldsEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var array
     */
    public array $nestedFields = [];
}
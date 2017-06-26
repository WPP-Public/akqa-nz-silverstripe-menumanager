<?php

/**
 * Class MobileMenuSiteConfig
 */
class MobileMenuSiteConfig extends DataExtension
{
    /**
     * @var array
     */
    private static $db = [
        'MenuManagerOption' => 'Boolean'
    ];

    /**
     * @var array
     */
    private static $has_many = [
        'MenuSets' => 'MenuSet'
    ];

    /**
     * @param FieldList $fields
     * @return FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab(
            "Root.MobileMenu",
            [
                LiteralField::create('Title', '<h2>Welcome to the mobile menu interface</h2>'),
                LiteralField::create('Content', '<p>This module allows you to pick what you want to display for your mobile menu</p>'),
            ]
        );

        $fields->addFieldToTab('Root.MobileMenu', new CheckboxField('MenuManagerOption', 'Using Menu manager instead of Children'));

        $fields->addFieldsToTab(
            "Root.MobileMenu",
            [
                DisplayLogicWrapper::create(PickerField::create(
                    'MenuSets',
                    'MenuSets',
                    $this->owner->MenuSets(),
                    'Select a Menu set'
                ))->displayIf("MenuManagerOption")->isChecked()->end(),

                DisplayLogicWrapper::create(LiteralField::create(
                    'TextWhenNoGridField',
                    '<h4>Display a picker gridfield if you check the above checkbox.</h4>'
                ))->displayIf("MenuManagerOption")->isNotChecked()->end()
            ]
        );

        return $fields;
    }
}

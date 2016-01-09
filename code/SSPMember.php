<?php

/**
 * Extends the Member object in Silverstripe
 */
class SSPMember extends DataExtension
{
    
    private static $db = array(
        'Username' => 'Varchar(256)'
    );
    
    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldToTab('Root.Main', new ReadonlyField('Username'));
    }
    
    public function updateSummaryFields(&$fields)
    {
        $fields['Username'] = 'Username (SSP)';
    }
}

<?php
namespace Craft;

class FeedMeService extends BaseApplicationComponent
{
	public function importNode($step, $node, $feed, $settings)
	{
        $canSaveEntry = true;

        // Print out our settings - which includes our fieldmapping
        craft()->feedMe_logs->log($settings, json_encode($settings->attributes), LogLevel::Info);

		// Protect from malformed data
        if (count($feed['fieldMapping'], false) != count($node, false)) {
            craft()->feedMe_logs->log($settings, Craft::t('FeedMeError - Columns and data did not match, could be due to malformed feed.'), LogLevel::Error);
        }

        // Get our field data via what we've mapped
        $fields = array();

        // Start looping through all the mapped fields - checking for nested nodes
        foreach ($feed['fieldMapping'] as $itemNode => $destination) {

            // Forget about any fields mapped as not to import
            if ($destination != 'noimport') {

                // Fetch the proper value for the field - dependant on type of feed
                if ($feed['feedType'] == FeedMe_FeedType::JSON) {
                    // TODO

                } else {
                    $fieldValue = craft()->feedMe_feedXML->getValueForNode($itemNode, $node);
                }

                $fields[$destination] = $fieldValue;
            }
        }


        echo '<pre>';
        print_r($fields);
        echo '</pre>';

		// Prepare an EntryModel (for this section and entrytype)
		$entry = craft()->feedMe_entry->setModel($feed);

        //
        // Check for Add/Update/Delete for existing entries
        //

        // Set criteria according to elementtype
        $criteria = craft()->feedMe_entry->setCriteria($feed);

        // If we're deleting, we only do it once, before the first entry is processed.
        // Don't forget, this is deleting all entries in the section/entrytype
        if ($feed['duplicateHandle'] == FeedMe_Duplicate::Delete) {

            // Only do this once man! You'll keep deleting entries we're adding otherwise...
            if ($step == 0) {

                // Get all elements to delete for section/entrytype
                $entries = $criteria->find();

                try {
                    // Delete
                    if (!craft()->feedMe_entry->delete($entries)) {
                        craft()->feedMe_logs->log($settings, Craft::t('FeedMeError - Something went wrong while deleting entries.'), LogLevel::Error);

                        return false;
                    }
                } catch (\Exception $e) {
                    craft()->feedMe_logs->log($settings, Craft::t('FeedMeError: ' . $e->getMessage() . '. Check plugin log files for full error.'), LogLevel::Error);

                    return false;
                }
            }
        }

        // Set up criteria model for matching
        $cmodel = array();
        foreach ($feed['fieldMapping'] as $key => $value) {
            if (isset($feed['fieldUnique'][$key]) && intval($feed['fieldUnique'][$key]) == 1 && !empty($fields[$value])) {
                $criteria->$feed['fieldMapping'][$key] = $cmodel[$feed['fieldMapping'][$key]] = $fields[$value];
            }
        }



        // If there's an existing matching entry
        if (count($cmodel) && $criteria->count()) {

            // If we're updating
            if ($feed['duplicateHandle'] == FeedMe_Duplicate::Update) {

                // Fill new EntryModel with match
                $entry = $criteria->first();

            // If we're adding, make sure not to overwrite existing entry
            } else if ($feed['duplicateHandle'] == FeedMe_Duplicate::Add) {
                $canSaveEntry = false;
            }
        }



        //
        //
        //

        if ($canSaveEntry) {
            
            // Prepare Element model (the default stuff)
            $entry = craft()->feedMe_entry->prepForElementModel($fields, $entry);

            try {
                // Hook to prepare as appropriate fieldtypes
                array_walk($fields, function(&$data, $handle) {
                    return craft()->feedMe_fields->prepForFieldType($data, $handle);
                });
            } catch (\Exception $e) {
                craft()->feedMe_logs->log($settings, Craft::t('Field FeedMeError: ' . $e->getMessage() . '. Check plugin log files for full error.'), LogLevel::Error);

                return false;
            }

            // Set our data for this EntryModel (our mapped data)
            $entry->setContentFromPost($fields);

            try {
                // Save the entry!
                if (!craft()->feedMe_entry->save($entry, $feed)) {
                    craft()->feedMe_logs->log($settings, $entry->getErrors(), LogLevel::Error);

                    return false;
                } else {
                    // Successfully saved/added entry
                    craft()->feedMe_logs->log($settings, Craft::t('Successfully saved entry ' . $entry->id), LogLevel::Info);
                }
            } catch (\Exception $e) {
                craft()->feedMe_logs->log($settings, Craft::t('Entry FeedMeError: ' . $e->getMessage() . '. Check plugin log files for full error.'), LogLevel::Error);

                return false;
            }
        }
	}

    public static function serialize_data_array(&$data)
    {
        if ( ! is_array($data)) return FALSE;

        foreach($data as &$item)
        {
            $item = serialize($item);
        }

        return $data;
    }
}

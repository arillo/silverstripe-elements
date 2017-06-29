<?php
namespace arillo\elements;

use \LeftAndMainExtension;
use \Convert;
use \DataObject;
use \SS_HTTPResponse_Exception;

class DefaultElementsExtension extends LeftAndMainExtension
{
    private static $allowed_actions = [ 'doCreateDefaults' ];

    public function doCreateDefaults($data, $form)
    {
        $className = $this->owner->stat('tree_class');
        $SQL_id = Convert::raw2sql($data['ID']);
        $record = DataObject::get_by_id($className, $SQL_id);
        $count = 0;

        if (!$record || !$record->ID) {
            throw new SS_HTTPResponse_Exception("Bad record ID #" . (int)$data['ID'], 404);
        }

        if ($relationNames = ElementsExtension::page_element_relation_names($record))
        {
            $defaultElements = $record->getDefaultElements();

            if (count($relationNames) > 0)
            {
                foreach ($relationNames as $relationName => $elementsClasses)
                {
                    if (isset($defaultElements[$relationName]))
                    {
                        $elementClasses = $defaultElements[$relationName];
                        foreach ($elementClasses as $className)
                        {
                            $definedElements = $record->ElementsByRelation($relationName)->map('ClassName', 'ClassName');
                            if (!isset($definedElements[$className]))
                            {
                                $element = new $className;
                                $element->populate('PageID', $SQL_id, $relationName);
                                $element->write();
                                $count++;
                            }
                        }
                    }
                }
            }
        }

        $this->owner->response->addHeader(
            'X-Status',
            rawurlencode("Created {$count} elements.")
        );

        return $this->owner
            ->getResponseNegotiator()
            ->respond($this->owner->request)
        ;
    }
}

<?php
class ElementsExtension extends DataExtension
{
    protected $_elementBaseClass;

    public function __construct($elementBaseClass = 'ElementBase')
    {
        parent::__construct();
        $this->_elementBaseClass = $elementBaseClass;
    }

   /* public static function parse_classes($relations)
    {
        if (!isset($relations))
        {
            user_error('you have to define the list of elements in your .yml config file');
        }

        $baseClass = 'ElementBase';
        $classes = [];

        foreach ($relations as $key => $value)
        {
            if (ClassInfo::exists($value) && (is_a(singleton($value), $baseClass)))
            {
                if ($label = singleton($value)->stat('singular_name'))
                {
                    $classes[$value] = $label;
                } else {
                    $classes[$value] = $value;
                }
            }
        }
        return $classes;
    }*/

    // define has_many based on elementClass
    public function extraStatics($class = null, $extension = null)
    {
        return array(
            'has_many' => array('Elements' => $this->_elementBaseClass)
        );
    }

    public function updateCMSFields(FieldList $fields)
    {
        if (!$this->owner->exists()) return;

        if ($realtionNames = $this->_elementRealtionNames())
        {
            foreach ($realtionNames as $key => $relationName)
            {
                $this->createGridFieldForRelation($fields, $relationName);
            }
        }
        // die;
    }

    public function getCMSElements()
    {
        return $this->owner
            ->Elements()
            ->sort(array('Sort' => 'ASC'))
        ;
    }

    public function onAfterPublish()
    {
        foreach($this->owner->Elements() as $element)
        {
            $element->write();
            $element->publish('Stage', 'Live');
        }
    }

    public function createGridFieldForRelation(FieldList $fields, $relationName)
    {
        if ($elementClasses = $this->_elementClassesForRealtion($relationName))
        {
            // sort relations
            asort($elementClasses);

            $config = GridFieldConfig_RelationEditor::create()
                ->removeComponentsByType('GridFieldDeleteAction')
                ->removeComponentsByType('GridFieldAddExistingAutocompleter')
            ;

            if (ClassInfo::exists('GridFieldOrderableRows'))
            {
                $config->addComponent(new GridFieldOrderableRows('Sort'));
            }

            if (count($elementClasses) > 1)
            {
                $config
                    ->removeComponentsByType('GridFieldAddNewButton')
                    ->addComponent($multiClass = new GridFieldAddNewMultiClass())
                ;

                // $multiClass->setClasses($elementClasses);
                $multiClass->setClasses($this->_elementClassesForDropdown($elementClasses));
            }

            $config
                ->getComponentByType('GridFieldPaginator')
                ->setItemsPerPage(50)
            ;

            $columns = [
                // 'Icon' => 'Icon',
                'singular_name'=> 'Type',
                'Title' => 'Title',
                'Languages' => 'Lang'
            ];

            if (count($elementClasses) == 1
                && $summaryFields = singleton($elementClasses[0])->summaryFields()
            ) {
                $columns = array_merge($columns, $summaryFields);
            }

            $config
                ->getComponentByType('GridFieldDataColumns')
                ->setDisplayFields($columns)
            ;

            $fields->addFieldToTab('Root.Elements',
                $gridfieldItems = GridField::create(
                    'Elements',
                    _t('ElementsExtension.Elements', 'Elements'),
                    $this->owner->getCMSElements(),
                    $config
                )
            );
        }
    }

    protected function _elementClassesForDropdown($elementClasses)
    {
        $result = [];
        foreach ($elementClasses as $elementClass)
        {
            $result[$elementClass] = $elementClass;
            if ($label = singleton($elementClass)->stat('singular_name'))
            {
                $result[$className] = $label;
            }
            return $result;
        }
    }

    protected function _elementRealtionNames()
    {
        if ($elementRelations = $this->owner->config()->element_relations)
        {
            return array_keys($elementRelations);
        }
        return false;
    }

    protected function _elementClassesForRealtion($relationName)
    {
        if ($elementRelations = $this->owner->config()->element_relations)
        {
            if (isset($elementRelations[$relationName]))
            {
                return array_filter($elementRelations[$relationName], function($className)
                {
                    if (ClassInfo::exists($className) && (is_a(singleton($className), $this->_elementBaseClass)))
                    {
                        return $className;
                    }
                });
            }
        }
        return [];
    }
}

# SilverStripe Elements

[![Latest Stable Version](https://poser.pugx.org/arillo/silverstripe-elements/v/stable?format=flat)](https://packagist.org/packages/arillo/silverstripe-elements)&nbsp;
[![Total Downloads](https://poser.pugx.org/arillo/silverstripe-elements/downloads?format=flat)](https://packagist.org/packages/arillo/silverstripe-elements)

Decorates a SiteTree class with multiple named element relations through a has_many "Elements" relation.

### Todo
+ write tests
+ write better docs

### Usage
Set up you relation types in your _config/elements.yml, e.g:

```yml
Page:
  element_relations:
    Elements:
      - Element
      - DownloadElement
    Downloads:
      - DownloadElement
```

In this example we are creating 2 element relationships to the Page, one called Elements, the other called Downloads.

To make it work `Element` class should subclass `ElementBase`, where all additional fields can be defined, e.g.:

```php
class Element extends ElementBase
{
    private static
        $singular_name = 'Base element',
        $db = [
            'Title' => 'Text',
            'Subtitle' => 'Text',
            ...
            ..
            .
        ]
    ;
}
```


In the SiteTree instance the element relations are now accessable through:

```php
$pageInst->ElementsByRelation('Elements');
$elementInst->ElementsByRelation('Elements');
```

To use them in a template:
```html
<% loop $ElementsByRelation(Elements) %>
  $Render($Pos, $First, $Last, $EvenOdd)
<% end_loop %>
```

__Notice:__ we pass in the $Pos, $First, $Last and $EvenOdd values to have them available inside the template as $IsPos, $IsFirst, $IsLast and $IsEvenOdd.

There is also a helper function to move a gridfield into another tab if you prefer:

```php
public function getCMSFields()
{
    $fields = parent::getCMSFields();
    // move the elements gridfield to a tab called 'PageElements'..
    $fields = ElementsExtension::move_elements_manager($fields, 'Elements', 'Root.PageElements');
    return $fields;
}
```

### Nested Element relations
Apply the same extension to the Element instead of the Page.

```yml
TeasersElement:
  element_relations:
    Teasers:
      - TeaserElement
```

### Element inheritance
If you would like to have the same elements applied to different Pagetypes you can use the `element_relations_inherit_from` definition referencing a arbitrary setup in the yml file. For example if we want the HomePage and the EventsPage to inherit the same elements we can define the .yml like this:

```yml
HomePage:
  element_relations_inherit_from: MainElements
EventsPage:
  element_relations_inherit_from: MainElements
```

They both reference the MainElements defined in the yml where you have defined the element_relations, like this:

```yml
MainElements:
  element_relations:
    Elements:
      - HeroElement
      - DownloadElement
      - TeasersElement
```

If you inherit elements you can still create your custom relations and also append new Element types to the inherited relation.

```yml
HomePage:
  element_relations_inherit_from: MainElements
  element_relations:
  Elements:
    - ImageElement
```

In this example ImageElement is added to the list of available Elements defined in MainElements.

### Translation
Naming of `Tab` and `GridField` labels can be done through silverstripes i18n.
There is a special key called `Element_Relations` reserved to accomplish this task, e.g. in de.yml:

```yml
de:
  Element_Relations:
    Downloads: 'Dateien'
```

### Populate default elements
A button below the Element GridField called "Create default elements" will populate the default elements defined in your _config.yml as empty elements in your page. If you trigger the action again it will counter-check against the already created elements and don't add any duplicates.

You can define the element_defaults for each of your relations like this:

```yml
Page:
  element_relations:
    Teasers:
      - TeaserElement
  element_defaults:
    Teasers:
      - TeaserElement
```

### Fluent integration
To use fluent with elements just add the Fluent extensions to the ElementBase:

```yml
ElementBase:
  extensions:
    - FluentExtension
    - FluentFilteredExtension
```


### Example: Bulkuploading with `colymba/gridfield-bulk-editing-tools`:

In some cases (e.g. slideshow elements, etc.) you may want to give content authors a way to upload many assets into a relation at once. Here we provide an example how this can be achieved.

**Note:** this will only work well with element relations with one single allowed child element class.

Install https://github.com/colymba/GridFieldBulkEditingTools

```
composer require colymba/gridfield-bulk-editing-tools
```

*(Watch out: grab a version fitting to you silverstripe version)*

You might need a to add a helper method to quickly add the gridfield component like this:

```php
// e.g. in Element.php, feel free to change to your needs....

/**
 * !!! CAUTION - only use in gridfields with one element relation !!!
 * Adds a bulkuploader to element GridField
 *
 * @param FieldList  $fields              the fields to look for the gf
 * @param string     $elementClass        the element class to create
 * @param string     $elementRelationName the relation name to add to the element
 * @param string     $assetRelationName   the asset relation inside the element
 * @return FieldList $fields
 */
public static function add_bulk_uploader(
    FieldList $fields,
    $elementClass,
    $elementRelationName,
    $assetRelationName,
    $uploadFolder = null
) {
    if ($gf = $fields->dataFieldByName($elementRelationName))
    {
        $gf->getConfig()
            ->addComponent((new GridFieldBulkUpload($assetRelationName, $elementClass))
                // this is needed in onBulkUpload hook
                ->setUfConfig('elementRelationName', $elementRelationName) 
                ->setUfSetup('setFolderName', $uploadFolder)
            )
        ;

        $gf->setModelClass($elementClass);
    }
    return $fields;
}
```

You also need to add an extension to your Element class to hook into the write process during bulk uploading:

```php
// feel free to change or use it in you own extension.
class BulkUploadExtension extends Extension
{
    public function onBulkUpload(GridField $gf)
    {
        // this is the part to apply in your code
        $this->owner->RelationName = $gf
            ->getConfig()
            ->getComponentByType('GridFieldBulkUpload')
            ->getUfConfig('elementRelationName')
        ;
    }
}

```

*(This is due to the way bulk editing tools work. Right now it only works with extension hooks. That might change in the future.)*

Then you can add the bulkuploader like this:

```php
// e.g. parent page or parent element
public function getCMSFields()
{
    $fields = parent::getCMSFields();
    Element::add_bulk_uploader(
        $fields,
        'ImageElement', // element class to create
        'Images', // element relation to attach to
        'Image', // asset relation 
        'home' // folder name
    );
    return $fields;
}
```


## Changelog:

### 0.2.0 
- remove DefaultElementsExtension
- add Publish page button in Element DetailForm

### 0.1.0 
- remove extensions from your mysite/_config/elements.yml

```yml
ElementBase:
  extensions:
    - arillo\elements\ElementsExtension

Page:
  extensions:
    - arillo\elements\ElementsExtension
```

# SilverStripe Elements

[![Latest Stable Version](https://poser.pugx.org/arillo/silverstripe-elements/v/stable?format=flat)](https://packagist.org/packages/arillo/silverstripe-elements)&nbsp;
[![Total Downloads](https://poser.pugx.org/arillo/silverstripe-elements/downloads?format=flat)](https://packagist.org/packages/arillo/silverstripe-elements)

Decorates a SiteTree class with multiple named element relations through a has_many "Elements" relation.

### Requirements

SilverStripe CMS ^4.0

For a SilverStripe 3.x compatible version of this module, please see the [1 branch, or 0.x release line](https://github.com/arillo/silverstripe-elements/tree/1.x).

### Todo

- write tests
- write better docs

### Usage

Set up you relation types in your \_config/elements.yml, e.g:

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
<% loop $ElementsByRelation(Elements) %> $Render($Pos, $First, $Last, $EvenOdd)
<% end_loop %>
```

**Notice:** we pass in the $Pos, $First, $Last and $EvenOdd values to have them available inside the template as $IsPos, $IsFirst, $IsLast and $IsEvenOdd.

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

### URLSegmentField

If you want to show the URLSegment field in the cms, you can opt-in via the config show_urlsegment_field

```yml
ElementBase:
  show_urlsegment_field: true
```

### Bulkuploader

There is support for using `Colymba\BulkUpload\BulkUploader` for an relation to a single element. E.g. given the following relation:

```yml
ImagesElement:
  element_relations:
    - ImageElement # an element with a has_one Image (Image) relation
```

you can apply a bulkuploader in `ImagesElement`:

```php
public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        if ($images = $fields->dataFieldByName('Images')) {
            BulkUploader::apply(
                $images,
                [
                    BulkUploader::ELEMENT_CLASSNAME => ImageElement::class,
                    BulkUploader::ELEMENT_RELATIONNAME => 'Images',
                    BulkUploader::FOLDER_NAME => 'FancyFolderName', // optional
                    BulkUploader::FILE_RELATIONNAME => 'File',  // optional, may be mandatory if you element has multiple has_one file relations
                ]
            );
        }
        return $fields;
    }
```

### Translation

Naming of `Tab` and `GridField` labels can be done through silverstripes i18n.
There is a special key called `Element_Relations` reserved to accomplish this task, e.g. in de.yml:

```yml
de:
  Element_Relations:
    Downloads: "Dateien"
```

### Populate default elements

A button below the Element GridField called "Create default elements" will populate the default elements defined in your \_config.yml as empty elements in your page. If you trigger the action again it will counter-check against the already created elements and don't add any duplicates.

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

## Changelog:

### 2.1.9

– added support for SS 4.4 GridFieldDetailForm::setShowAdd

### 2.1.8

- addded support for bulkuploading

### 2.1.0

- removed Fluent Locales auto-creation, use [`Arillo\Utils\FluentFilteredHelper`](https://github.com/arillo/silverstripe-utils/blob/master/src/FluentFilteredHelper.php) instead.

- refactor GridField overview display: added `getCMSTypeInfo` and `getCMSSummary` functions.

### 2.0.0

- SilverStripe 4 compat

### 0.2.0

- remove DefaultElementsExtension
- add Publish page button in Element DetailForm

### 0.1.0

- remove extensions from your mysite/\_config/elements.yml

```yml
ElementBase:
  extensions:
    - arillo\elements\ElementsExtension

Page:
  extensions:
    - arillo\elements\ElementsExtension
```

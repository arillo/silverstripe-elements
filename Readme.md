# webtoolkit\elements

Decorates a SiteTree class with multiple named element relations through a has_many "Elements" relation.

### Todo

+ make composer ready
+ write tests
+ ElementBase -> has_many: Elements

### Usage
Add the extension to a SiteTree class and set up you relation types, e.g:

```
Page:
  extensions:
    - webtoolkit\elements\ElementsExtension("Element")

  element_relations:
    Elements:
      - Element
      - DownloadElement
    Downloads:
      - DownloadElement
```

To make it work `Element` class should subclass `ElementBase`, where all additional fields can be defined, e.g.:

```
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


In the SiteTree instance the realtions elements are now accessable through:

```
$pageInst->getItemsByRelation('Elements');
```


### Translation
Naming of `Tab` and `GridField` labels can be done through silverstripes i18n.
There is a special key called `Element_Relations` reserved to accomplish this task, e.g. in de.yml:

```
de:
  Element_Relations:
    Downloads: 'Dateien'
```


### Betterbuttons integration

Add to your _config.yml:

```
BetterButtonsActions:
  edit:
    BetterButtonFrontendLinksAction: false
  versioned_edit:
    BetterButton_Rollback: true
    BetterButton_Unpublish: true
    Group_Versioning: false
    BetterButtonFrontendLinksAction: false
```

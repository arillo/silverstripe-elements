# arillo\elements

Decorates a SiteTree class with multiple named element relations through a has_many "Elements" relation.

### Todo
+ write tests
+ write better docs

### Usage
Add the extension to a SiteTree class and set up you relation types, e.g:

```
Page:
  extensions:
    - arillo\elements\ElementsExtension("Element")

  element_relations:
    Elements:
      - Element
      - DownloadElement
    Downloads:
      - DownloadElement
```

In this example we are creating 2 element relationships to the Page, one called Elements, the other called Downloads.

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


In the SiteTree instance the element relations are now accessable through:

```
$pageInst->ElementsByRelation('Elements');
$elementInst->ElementsByRelation('Elements');
```

To use them in a template:
```
	<% loop $ElementsByRelation(Elements) %>
		$Render
	<% end_loop %>
```

There is also a helper function to move a gridfield into another tab if you prefer:

```
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

```

ElementBase:
  extensions:
    - arillo\elements\ElementsExtension("Element")

TeasersElement:
  element_relations:
    Teasers:
      - TeaserElement
```

### Translation
Naming of `Tab` and `GridField` labels can be done through silverstripes i18n.
There is a special key called `Element_Relations` reserved to accomplish this task, e.g. in de.yml:

```
de:
  Element_Relations:
    Downloads: 'Dateien'
```

### Populate default elements
You will get a new action called "Create default elements" which will be hidden inside the "More Options" button next to the Publish button in the CMS.
It will populate the default elements defined in your _config.yml as empty elements in your page. If you trigger the action again it will check for existance of the element Class and won't add Classes that are already created.

To use the populate defaults behaviour add the following extension in your _config.yml

```
LeftAndMain:
  extensions:
    - DefaultElementsExtension
```

Then you can define the element_defaults for each of your relations like this:

```
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

```
ElementBase:
  extensions:
    - FluentExtension
    - FluentFilteredExtension
```

# styletile_twig_extension
Twig extension for rendering styletile from twig files with test data.

## About
Extension provides method which will return styletile resulting object with array of rendered html and navigation data (between twig files and folders).


## Usage
### Add to project
Require php file
```
require_once('styletile-twig-extension/styletile-twig-extension.php');
```

Add extension to twig. Pass folder which you want to include in style tile
```
$twig->addExtension( new TwigStyleTile\StyleTile_Twig_Extension('/partials') );
```

### Outputing styletile
Use twig file to output styletile result data. This way you can customize look of styletile.

Call it in twig file (styletile.twig) and pass in current file path and url, where you browse style tile.
URL parameter is used to generate navigation links by adding to it GET parameter _styletile:
```
style_tile('pages/styletile/styletile.twig', '/styletile')
{% set _style_tile = style_tile('styletile.twig', '/styletile') %}
```
Returned object has two properties:
1. html - rendering result object: 
  *   html - contains rendered templates html
  *   name - template file name
2. nav - navigation data array of objects which consists of:
  *   link - link to folder or individual twig file. for exaple styletile/?_styletile=folder,test.twig, styletile/?_styletile=folder
  *   title - template file name
  *   items - array of similar nav objects (if folder)

#### Output HTML
```
{% for html_template in _style_tile.html %}
<div>
    <header >
        <h2 >
            <span>{{ html_template.file_name }}</span>
        </h2>

    </header>
    <section>
        {{ html_template.html }}
    </section>
</div>
{% endfor %}
```

#### Output Navigation
```
<ul>
    {% for nav in _style_tile.nav %}
        <li class="st-nav__item">{% include 'recursive-nav.twig' with nav only %}</li>
    {% endfor %}
</ul>
```
recursive-nav.twig
```
<a href="{{ link }}">{{ title }}</a>
{% if items|length > 0 %}
	<ul class="st-nav__sublist">
		{% for item in items %}
			<li>{% include 'recursive-nav.twig' with item only %}</li>
		{% endfor %}
	</ul>
{% endif %}

```

<?php
# Copyright (C) 2010 Jean-Jacques Puig
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <http://www.gnu.org/licenses/>.

/*
Modifier par Phenix pour ajouter le support des images de SPIP 3, enjoy !
Attention qu'il faut maintenant passer l'id_article pour lier le document et l'article !
*/

class HTML2SPIP3Engine extends HTMLEngine {

  # Headings related members (H1, H2, H3...)
  private $headingDepthSpip = "{{{";
  private $headingDepthHTML = null;

  # Lists related members (UL, OL, LI...)
  private $listType = array();
  private $listDepth = 0;

  # Tables related members (TABLE, TH, TR, TD...)
  private $tablesStack = array();

  # Within marks
  private $withinStack = array();

  # SPIP Environment
  private $spipDBResource = null;
  private $spipImagesPath = null;
  private $id_article = null;

  # Documents (images) injected in SPIP database
  public $spipDocumentsIds = array();

  # Common substitutions
  protected $tag2typo = array(
    'br' => "\n_ ",
    'hr' => "\n----",
    'p' => "\n\n",
  );

  # Main substitution rules
  # protected $attributeOuterSubstitute = array(
  # );

  protected $attributeInnerSubstitute = array(
    'align' => '_align',
    'style' => '_style',
  );

  protected $tagSubstitute = array(
    # array() as 3rd arg forces detail
    # display of ignored attributes

    '#document' => null,
    'html' => null,
    'body' => null,
    'meta' => null,
    'link' => null,
    'style' => null,

    'script' => array('_identityS', '_identityE', array('type')),
    'embed' => array('_identityS', '_identityE', null),
    'param' => array('_identityS', '_identityE', null),
    'object' => array('_identityS', '_identityE', null),

    'div' => array('_div', '_div', array('class', 'style', 'align')),

    'h1' => array('_hiS', '_hiE', array('class')),
    'h2' => array('_hiS', '_hiE', array('class')),
    'h3' => array('_hiS', '_hiE', array('class')),
    'h4' => array('_hiS', '_hiE', array('class')),
    'h5' => array('_hiS', '_hiE', array('class')),
    'h6' => array('_hiS', '_hiE', array('class')),

    'p' => array('_p', '_p', array('class', 'style', 'align')),
    'br' => array('_br', ''),

    'ul' => array('_ulOrOlS', '_ulOrOlE'),
    'ol' => array('_ulOrOlS', '_ulOrOlE'),
    'li' => array('_li', '_li', array('style')),

      # TABLES related tags

    'table' => array('_tableS', '_tableE', array(
                        'summary', 'cellpadding', 'cellspacing',
                        'border', 'width', 'class', 'style'
                       )),
    'caption' => array('_captionS', '_captionE', array()),
    'tr' => array('_trS', '_trE', array('style')),
    'th' => array('_thOrTdS', '_thOrTdE', array(
                        'colspan', 'rowspan', 'width', 'bgcolor', 'scope'
                       )),
    'td' => array('_thOrTdS', '_thOrTdE', array(
                        'colspan', 'rowspan', 'width', 'style', 'scope'
                       )),

    'colgroup' => array('', '', array()),
    'col' => array('', '', array()),
    'thead' => array('', '', array()),
    'tbody' => array('', '', array()),
    'tfoot' => array('', '', array()),


    'font' => array('', '', array(
                        'face', 'size', 'color',
                       )),
    'b' => array('_strongS', '_strongE', array('style')),
    'strong' => array('_strongS', '_strongE'),
    'blockquote' => array('_quoteS', '_quoteE', array('class')),
    'code' => array('_codeS', '_codeE', array('class')),
    'textarea' => array('_cadreS', '_cadreE', array('class')),
    'em' => array('_emS', '_emE'),
    'i' => array('_emS', '_emE'),
    'span' => array('_spanS', '_spanE', array('id', 'class')),
    'hr' => array("_tag2typo", ''),
    'u' => array('', '', array()),
    'sup' => array('_supS', '_supE', array()),
    'sub' => array('_subS', '_subE', array()),
    'strike' => array('_delS', '_delE', array()),
    'del' => array('_delS', '_delE', array()),

    'a' => array('_aS', '_aE', array(
                        'title', 'href', 'target', 'style', 'name', 'class',
                        'rel'
                       )),
    'img' => array('_imgS', '', array(
                        'src', 'align', 'width', 'height', 'alt', 'title',
                        'hspace', 'vspace', 'border', 'class', '_cke_saved_src',
                       )),

    '#text' => '_raw',
    '#cdata-section'=> '_raw',
    '#comment' => '_comment',
  );

  protected function getAlign($align, $part) {
    switch ($align) {
      case 'right':
        return ($part == TAG_START) ? '[/ ' : ' /]';

      case 'center':
      case 'middle':
        if (!$this->within('_thOrTd') && !$this->within('_tr'))
          return ($part == TAG_START) ? '[| ' : ' |]';
    }
  }

  # protected function getJustification($attributes, $part) {
  # if (
  # array_key_exists('style', $attributes)
  # && preg_match('/text-align: (center|justify|left|right)/i', $attributes['style'], $matches))
  # $align = $matches[1];
  # elseif (array_key_exists('align', $attributes))
  # $align = $attributes['align'];
  # else
  # $align = null;

  # return $this->getAlign($align, $part);
  # }

  protected function _align($attribute, $value, $tag, $textContent, $part) {
    return $this->getAlign($value, $part);
  }

  protected function _style($attribute, $value, $tag, $textContent, $part) {
      if (preg_match('/text-align: (center|justify|left|right)/i', $value, $matches))
        return $this->getAlign($matches[1], $part);
  }

  protected function _identityS($tag, $attributes) {
    $data = "<$tag";

    foreach($attributes as $name => $value)
      $data .= " $name=\"$value\"";

    $data .= '>';

    return $data;
  }

  protected function _identityE($tag) {
    return "</$tag>";
  }

  protected function _div($tag, $attributes, $text, $part) {
    if (!strlen(trim($text)))
      return;

    if (array_key_exists('class', $attributes)
        && (preg_match('/\btexteencadre-spip\b/', $attributes['class']))
        && (preg_match('/\bspip\b/', $attributes['class']))) {
        $chunk = '_divtextencadre';
        switch ($part) {
          case TAG_START:
            $this->openChunk($chunk);
            return;

          case TAG_END:
            $this->closeChunk($chunk);
            $data = $this->readChunk($chunk);
            $this->deleteChunk($chunk);
            return $this->tag2typo['p'] . '[(' . trim($data) . ')]';
      }
    }
  }

  protected function _hiS($tag, $attributes, $text) {
    $this->pushWithin('_hi');

    if (!strlen(trim($text)))
      return;

    if ($this->headingDepthHTML != null) {
      $depth_diff = strcmp($tag, $this->headingDepthHTML);
      $verity = ($depth_diff == 0) ? 0 : ($depth_diff / abs($depth_diff));
    } else
      $verity = 0;

    $this->headingDepthHTML = $tag;

    switch($verity) {
      case -1:
        $diff = strcmp($tag, 'h1');
        $depth = ($diff > 1) ? str_repeat('*', $diff) : '';
        $this->headingDepthSpip = "{{{" . $depth;
        break;

      case 1:
        if ($this->headingDepthSpip == "{{{")
          $this->headingDepthSpip .= '**';
        else
          $this->headingDepthSpip .= '*';
        break;
    }

    $this->openChunk($this->headingDepthSpip);

    return $this->headingDepthSpip;
  }

  protected function _hiE($tag, $attributes, $text) {
    $this->popWithin('_hi');

    if (!strlen(trim($text)))
      return;

    $this->push('}}}');
    $this->closeChunk($this->headingDepthSpip);

    $data = $this->readChunk($this->headingDepthSpip);
    $data = preg_replace("/\n+_ /", "\n", $data);
    $data = preg_replace("/\n+/", "}}}\n$this->headingDepthSpip", $data);

    $this->deleteChunk($this->headingDepthSpip);

    return $data;
  }

  protected function _p($tag, $attributes, $text, $part) {
    // if (!strlen(trim($text)))
    // return;

    return (($part == TAG_START) ? $this->tag2typo[$tag] : '');
  }

  protected function _br($tag) {
    if (
      (!$this->within('_a'))
      && (!$this->within('_hi'))
    )
      return $this->tag2typo[$tag];

    if ($this->within('_hi'))
      return "\n";

    return;
  }

  protected function _tag2typo($tag) {
    return $this->tag2typo[$tag];
  }

  protected function _ulOrOlS($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    array_unshift($this->listType, $tag);
    $this->listDepth++;
  }

  protected function _ulOrOlE($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    array_shift($this->listType);
    if ($this->listDepth > 0)
      $this->listDepth--;
    else
      return "\n";
  }

  protected function _li($tag, $attributes, $text, $part) {
    if (!strlen(trim($text)))
      return;

    if ($part == TAG_END)
      return;
      # return $this->getJustification($attributes, $part);

    if (sizeof($this->listType)) # This should not happen, but remember input comes from users
      $listType = $this->listType[0];
    else
      $listType = 'ul';

    switch ($listType) {
      case 'ol':
        return "\n-" . str_repeat('#', $this->listDepth) . ' '; # . $this->getJustification($attributes, $part);

      default:
        return "\n-" . str_repeat('*', $this->listDepth) . ' '; # . $this->getJustification($attributes, $part);
    }
  }


  protected function _tableS($tag, $attributes) {
    array_unshift(
      $this->tablesStack,
      array(
        'row' => 0,
        'col' => 0,
        'map' => array(),
        'newrow' => true,
      )
    );

    if (array_key_exists('summary', $attributes))
      $this->tablesStack[0]['summary'] = $attributes['summary'];

    return "\n"; # AIDE SPIP: 'Il est impératif de laisser des lignes vides
                  # avant et après ce tableau.
  }

  protected function _tableE() {
    array_shift($this->tablesStack);

    return "\n"; # AIDE SPIP: 'Il est impératif de laisser des lignes vides
                  # avant et après ce tableau.
  }

  protected function popTableSummary() {
    if (array_key_exists('summary', $this->tablesStack[0])) {
      $summary = $this->tablesStack[0]['summary'];
      unset($this->tablesStack[0]['summary']);
    } else
      $summary = '';

    return $summary;
  }

  protected function _captionS($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    return "\n|| ";
  }

  protected function _captionE($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    return ' | ' . $this->popTableSummary() . ' ||';
  }

  protected function _trS() {
    $this->pushWithin('_tr');

    $this->tablesStack[0]['newrow'] = true;
    $this->tablesStack[0]['row']++;
    $this->tablesStack[0]['col'] = 0;

    if (strlen($summary = $this->popTableSummary()))
      return "\n|| | $summary ||\n";
    else
      return "\n";
  }

  protected function _trE() {
    $this->popWithin('_tr');
  }

  protected function _thOrTdS($tag, $attributes) {
    $this->pushWithin('_thOrTd');

    $this->tablesStack[0]['col']++;

    if ($this->tablesStack[0]['newrow'] == true) {
      $this->tablesStack[0]['newrow'] = false;
      $data = '|';
    } else
      $data = '';

    while (
      ($index = $this->tablesStack[0]['col'] . 'x' . $this->tablesStack[0]['row'])
      && (array_key_exists(
        $index,
        $this->tablesStack[0]['map']
      ))
    ) {
      unset($this->tablesStack[0]['map'][$index]);
      $data .= ' ^ |';
      $this->tablesStack[0]['col']++;
    }

    if ($tag == 'th')
      $data .= ' {{';
    else
      $data .= ' ';

    return $data;
  }

  protected function _thOrTdE($tag, $attributes) {
    $this->popWithin('_thOrTd');

    if ($tag == 'th')
      $data = '}} |';
    else
      $data = ' |';

    if (array_key_exists('colspan', $attributes))
      $colspan = $attributes['colspan'];
    else
      $colspan = 1;

    $row = $this->tablesStack[0]['row'];

    if (array_key_exists('rowspan', $attributes)) {
      $rowspan = $attributes['rowspan'];
      $col = $this->tablesStack[0]['col'];
      for ($j = 1; $j < $rowspan; $j++)
        for ($i = 0; $i < $colspan; $i++)
          $this->tablesStack[0]['map'][($i + $col) . 'x' . ($j + $row)] = true;
    }

    for ($i = 2; $i <= $colspan; $i++) {
      $data .= ' < |';
      $this->tablesStack[0]['col']++;
    }

    while (
      ($index = ($this->tablesStack[0]['col'] + 1) . 'x' . $row)
      && (array_key_exists(
        $index,
        $this->tablesStack[0]['map']
      ))
    ) {
      unset($this->tablesStack[0]['map'][$index]);
      $data .= ' ^ |';
      $this->tablesStack[0]['col']++;
    }

    return $data;
  }

  protected function _codeS($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    return ' <code>';
  }

  protected function _codeE($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    return '</code> ';
  }

  protected function _cadreS($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    return ' <cadre>';
  }

  protected function _cadreE($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    return '</cadre> ';
  }

  protected function _quoteS($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    if (array_key_exists('class', $attributes))
      if (preg_match('/\bspip_poesie\b/', $attributes['class']))
        return ' <poesie>';

    return ' <quote>';
  }

  protected function _quoteE($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    if (array_key_exists('class', $attributes))
      if (preg_match('/\bspip_poesie\b/', $attributes['class']))
        return '</poesie> ';

    return '</quote> ';
  }

  protected function _strongS($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    if (array_key_exists('class', $attributes))
      if (preg_match('/\bcaractencadre-spip\b/', $attributes['class']))
        return ' [*';

    return ' {{';
  }

  protected function _strongE($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    if (array_key_exists('class', $attributes))
      if (preg_match('/\bcaractencadre-spip\b/', $attributes['class']))
        return '*] ';

    return '}} ';
  }

  protected function _emS($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    return ' {';
  }

  protected function _emE($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    return '} ';
  }

  protected function _supS($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    return '<sup>';
  }

  protected function _supE($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    return '</sup>';
  }

  protected function _subS($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    return '<sub>';
  }

  protected function _subE($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    return '</sub>';
  }

  protected function _delS($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    return '<del>';
  }

  protected function _delE($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    return '</del>';
  }

  protected function _spanS($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    if (!array_key_exists('class', $attributes))
      return;

    switch ($attributes['class']) {
      case 'chapo':
        if (!$this->within('_a'))
          return ' [*';
    }
  }

  protected function _spanE($tag, $attributes, $text) {
    if (!strlen(trim($text)))
      return;

    if (!array_key_exists('class', $attributes))
      return;

    switch ($attributes['class']) {
      case 'chapo':
        if (!$this->within('_a'))
          return '*] ';
    }
  }

  protected function _aS($tag, $attributes, $text) {
    $this->pushWithin('_a');

    $name = (array_key_exists('name', $attributes))
            ? $attributes['name']
            : false;

    $href = (array_key_exists('href', $attributes))
            ? $attributes['href']
            : false;

    $has_title = (array_key_exists('title', $attributes))
            ? true
            : false;

    $data = '';

    if (strlen(trim($name)))
      $data .= "[$name<-]";

    if (!strlen($text))
      return $data;

    if (!($has_title) && (preg_match('/^mailto:/', $href)))
      return $data;

    return $data. ' [';
  }

  protected function _aE($tag, $attributes, $text) {
    $this->popWithin('_a');

    if (!strlen($text))
      return;

    $href = (array_key_exists('href', $attributes))
            ? $attributes['href']
            : false;

    $title = (array_key_exists('title', $attributes))
            ? $attributes['title']
            : false;

    $data = '';

    if (!($title) && (preg_match('/^mailto:/', $href)))
      return;

    if ($title)
      $data .= "|$title";

    $data .= '->';

    if (!(preg_match('/^mailto:/', $href)))
      $data .= $href;

    $data .= '] ';

    return $data;
  }

  protected function _imgS($tag, $attributes) {
    if (array_key_exists('class', $attributes) &&
        ($attributes['class'] == 'puce'))
      return "\n-";
     
    if (!array_key_exists('src', $attributes))
      return;

    $url = $attributes['src'];
    if (preg_match('|^file:///|', $url) > 0)
      return;

    switch ($url[0]) {
      case '/':
        $url = "http://" . $_SERVER['SERVER_NAME'] . $url;
        break;

      default:
      # $this->interrupt("Unexpected URL: $href");
    }

    if (array_key_exists('align', $attributes))
      $align = '|' . $attributes['align'];
    else
      $align = '';

    $image_name = preg_replace('|^.*/([^/]*)$|', '$1', $url);

    $width = array_key_value_if_notEmpty('width', $attributes, 0);
    $height = array_key_value_if_notEmpty('height', $attributes, 0);
    $titre = utf8_decode(array_key_value_if_notEmpty('alt', $attributes, $image_name));
    $descriptif = utf8_decode(array_key_value_if_notEmpty('title', $attributes, $image_name));

    $id = spip_add_document(
      $this->id_article,
      $url,
      $titre
    );
    
    $this->spipDocumentsIds[] = $id;
    
    return "<img$id$align>";
  }

  protected function _raw($tag, $text) {
    if ($this->within('_tr'))
      return trim($text);

    return $text;
  }

  protected function _comment($tag, $text) {
    $this->info("Commentaire: $text\n");
  }

  protected function postProcessing($data, $chunkTitle = 'default') {
    $replacements = array(
      "/[ \t]+/"
        => ' ',

      "/({{{)+/"
        => "\n\n{{{",

      "/(}}})+/"
        => "}}}",
 
      "/\s{{{}}}/"
        => '',
    );

    $spans = array(
      ' {', '} ',
      ' {{', '}} ',
      ' [', '] ', # hyperlinks
      ' [*', '*] ',
      ' [**', '**]',
      '[| ', ' |]',
      '[/ ', ' /]',
    );
    foreach($spans as $span)
      $replacements["/(" . preg_quote($span, '/') . ")+/"] = $span;

    for ($i = 0; $i < sizeof($spans); $i += 2)
      $replacements["/" . preg_quote($spans[$i] . $spans[$i+1], '/') . "/"] = '';

    $replacements[ "/(\n\n)\n+/" ] = "\n\n";
    $replacements[ "/(\n_ ~)+/" ] = "\n_ ";
    $replacements[ "/(\n_ \n)+/" ] = "\n\n";
    $replacements[ "/\n_ $/" ] = "\n";

    foreach($spans as $span) {
      $ts = preg_quote(trim($span), '/');
      $replacements[ "/([a-z]') *(" . $ts. "[aehiouy])/i" ] = "$1$2";
    }

    return preg_replace(
            array_keys($replacements),
            array_values($replacements),
            $data
    );
  }

  protected function preProcessing($data) {
    $data = html_entity_decode($data, ENT_COMPAT, 'UTF-8');

    $replacements = array();

    $block_tags = array('p', 'h[1-6]');

    foreach($block_tags as $bt)
      $replacements["|(<$bt(\s+[^>]*)?>)\s*([^\s].*[^\s])\s*(</$bt>)|SsiU"]
        = '$1$3$4';

    $other_rep = array(
      '|(<td(\s+[^>]*)?>)\s*<p(\s+[^>]*)?>(.*)</p>\s*(</td>)|SsiU'
        => '$1$4$5',

      '|<br />\s*(<img alt="-" class="puce")|si'
        => '$1',

      '|<p>&nbsp;</p>|Si'
        => '',

      "/\s+/S"
        => ' ',

      "/&nbsp;/S"
        => '~',

      '|</caption>\s*<|si'
        => '</caption><',
    );

    foreach($other_rep as $repp => $repv)
      $replacements[$repp] = $repv;

    $data = preg_replace(
      array_keys($replacements),
      array_values($replacements),
      $data);

    return $data;
  }

  # $spip_db_resource no longer useful; any value OK
  public function __construct($spip_db_resource, $images_path, $id_article) {
    $this->spipDBResource = $spip_db_resource;
    $this->spipImagesPath = $images_path;
    $this->id_article = $id_article;
  }

  public function addIdentityTags($tags) {
foreach ($tags as $tag)
$this->tagSubstitute[$tag] =
array('_identityS', '_identityE', null);
  }

  protected function pushWithin($tag) {
    array_unshift($this->withinStack, $tag);
  }

  protected function popWithin($tag) {
    if ($this->withinStack[0] == $tag)
      array_shift($this->withinStack);
    else
      $this->error("Incorrect nesting tags stack; expected $tag, found "
                    . $this->withinStack[0]);
  }

  protected function within($tag) {
    return in_array($tag, $this->withinStack);
  }

}

?>
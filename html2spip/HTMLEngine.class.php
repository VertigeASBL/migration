<?php # vim: syntax=php tabstop=2 softtabstop=2 shiftwidth=2 expandtab textwidth=80 autoindent
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

define('TAG_START', 'tag_start');
define('TAG_END', 'tag_end');

class HTMLEngine {

  protected $attributeOuterSubstitute = array();
  protected $tagSubstitute = array();
  protected $attributeInnerSubstitute = array();

  private $chunkTitle = array('default');
  private $chunkData = array('default' => '');

  public function translate($htmlString) {
    if (method_exists($this, 'preProcessing'))
      $htmlString = $this->preProcessing($htmlString);

    if (($htmlString === null) || trim($htmlString) == '') {
      $this->push($htmlString);

      return $this->chunkData;
    }

    libxml_use_internal_errors(true);
    if (!($doc = DOMDocument::loadHTML($htmlString))) {
      $this->info($this->libXMLErrorsToString($htmlString));
      $this->error('Fatal parse error');

      return false;
    }
    $this->info($this->libXMLErrorsToString($htmlString));

    $this->recurseNode($doc);

    # Might have been delegated to postProcessing
    foreach($this->chunkData as $key => $value)
      $this->chunkData[$key] = utf8_decode($value);

    if (method_exists($this, 'postProcessing'))
      foreach($this->chunkData as $key => $value)
        $this->chunkData[$key] = $this->postProcessing($value, $key);

    return $this->chunkData;
  }

  # Node analysis and rule(s) lookup
  private function recurseNode($domNode) {
    $nName = $domNode->nodeName;

    $nAttributes = array();
    if ($domNode->attributes != null)
      foreach($domNode->attributes as $aName => $aNode)
        $nAttributes[$aName] = $aNode->nodeValue;

    $nText = $domNode->textContent;

    if (!array_key_exists($nName, $this->tagSubstitute)) {
      $this->processAttributesOuter($nAttributes, $nName, $nText, TAG_START);
      $this->processAttributesInner($nAttributes, $nName, $nText, TAG_START);
      $this->warn("Tag of type $nName ignored");
      $this->recurseChilds($domNode);
      $this->processAttributesInner($nAttributes, $nName, $nText, TAG_END);
      $this->processAttributesOuter($nAttributes, $nName, $nText, TAG_END);

      return;
    }

    $tagSub = $this->tagSubstitute[$nName];

    switch ($nName) {
      case '#text':
      case '#comment':
      case '#cdata-section':
        $this->processText($nName, $nText, $tagSub);

        return;
    }

    if (!is_array($tagSub)) {
      if (sizeof($nAttributes) > 0)
        $this->info("No specific attributes processing for tag $nName with
attributes " . implode(', ', array_keys($nAttributes)));

      $this->processTag($domNode, $nName, $nAttributes, $nText, array(TAG_START => $tagSub, TAG_END => $tagSub));

      return;
    }

    $size = sizeof($tagSub);

    if (($size < 2) || ($size > 3))
        $this->interrupt("Wrong rule specification for tags $nName");

    if (($size == 2) && ($tagSub[1] === null)) {
      $this->processTag($domNode, $nName, $nAttributes, $nText, array(TAG_START => $tagSub[0], TAG_END => $tagSub[0]));

      return;
    }

    if (($size == 2) && (sizeof($nAttributes) > 0))
        $this->info("No specific attributes processing for tag $nName with
attributes " . implode(', ', array_keys($nAttributes)));

    if (($size == 3) && ($tagSub[2] !== null)) {

      if (!is_array($tagSub[2]))
        $this->interrupt("Wrong rule specification for tags $nName");

      $attrDiff = array_diff_key_value($nAttributes, $tagSub[2]);
      if (sizeof($attrDiff) > 0)
        $this->info("No specific attributes processing some attributes of tag
$nName: " . print_r($attrDiff, true));
    }

    $this->processTag($domNode, $nName, $nAttributes, $nText, array(TAG_START => $tagSub[0], TAG_END => $tagSub[1]));
  }

  # Recursion dispatch
  private function recurseChilds($domNode) {
    if ($domNode->hasChildNodes())
      foreach ($domNode->childNodes as $node)
        $this->recurseNode($node);
  }

  # Rules processing
  private function activeRule($rule) {
    if ($rule === null)
      return false;

    if (!is_string($rule))
      $this->interrupt("Wrong rule specification: $rule");

    return true;
  }

  private function processRule($tag, $attributes, $textContent, $rule, $part = null) {
    if (!$this->activeRule($rule))
      return;

    if (preg_match('/^_[^_\s]+/', $rule) > 0) {
      $this->push($this->{$rule}($tag, $attributes, $textContent, $part));
    } elseif (preg_match('/^__[^\s]+/', $rule) > 0) {
      $this->push(substr($rule, 1));
    } else {
      $this->push($rule);
    }
  }

  private function processAttributes($rulesArray, $attributes, $tag,
                                      $textContent, $part) {
    foreach($attributes as $key => $value) {
      if (array_key_exists($key, $rulesArray)) {

        $rules = $rulesArray[$key];

        if (is_string($rules)) {
          $rule = $rules;
        } elseif (is_array($rules) && sizeof($rules) == 2) {
          switch($part) {
            case TAG_END:
              $rule = $rules[1];
              break;

            case TAG_START:
            default:
              $rule = $rules[0];
              break;
          }
        } else
          $this->interrupt("Wrong rules specification for attribute $key: " .
                              print_r($rules, true));

        if ($this->activeRule($rule)) {
          if (preg_match('/^_[^_\s]+/', $rule) > 0)
            $this->push($this->{$rule}($key, $value, $tag, $textContent, $part));
          elseif (preg_match('/^__[^\s]+/', $rule) > 0)
            $this->push(substr($rule, 1));
          else
            $this->push($rule);
        }
      }
    }
  }

  private function processAttributesOuter($attributes, $tag, $textContent, $part) {
    $this->processAttributes(
      $this->attributeOuterSubstitute,
      $attributes,
      $tag,
      $textContent,
      $part
    );
  }

  private function processAttributesInner($attributes, $tag, $textContent, $part) {
    $this->processAttributes(
      $this->attributeInnerSubstitute,
      $attributes,
      $tag,
      $textContent,
      $part
    );
  }

  private function processTag($domNode, $tagName, $tagAttributes, $tagTextContent, $tagRules) {
    $this->processAttributesOuter($tagAttributes, $tagName, $tagTextContent, TAG_START);
    $this->processRule($tagName, $tagAttributes, $tagTextContent, $tagRules[TAG_START], TAG_START);
    $this->processAttributesInner($tagAttributes, $tagName, $tagTextContent, TAG_START);
    $this->recurseChilds($domNode);
    $this->processAttributesInner($tagAttributes, $tagName, $tagTextContent, TAG_END);
    $this->processRule($tagName, $tagAttributes, $tagTextContent, $tagRules[TAG_END], TAG_END);
    $this->processAttributesOuter($tagAttributes, $tagName, $tagTextContent, TAG_END);
  }

  private function processText($tag, $textContent, $rule) {
    if (!$this->activeRule($rule))
      return;

    if (preg_match('/^_[^_\s]+/', $rule) > 0) {
      $this->push($this->{$rule}($tag, $textContent));
    } elseif (preg_match('/^__[^\s]+/', $rule) > 0) {
      $this->push(substr($rule, 1));
    } else {
      $this->push($rule);
    }
  }

  # Data output organization functions: allows for 'cutting'
  # html document in parts
  protected function openChunk($chunkTitle) {
    array_unshift($this->chunkTitle, $chunkTitle);
    $this->chunkData[$chunkTitle] = '';
  }

  protected function closeChunk($chunkTitle = null) {
    if (sizeof($this->chunkTitle) == 1)
      $this->error('Attempt to close default chunk !');
    else {
      $chunk = array_shift($this->chunkTitle);
      if (($chunkTitle !== null) && (strcmp($chunk, $chunkTitle)))
        $this->error('Attempt to close unmatching chunk ' . $chunkTitle
                      . ' with ' . $chunk);
    }
  }

  protected function deleteChunk($chunkTitle) {
    if ($chunkTitle == 'default')
      $this->error("Attempt to delete default chunk !");
    elseif (!array_key_exists($chunkTitle, $this->chunkData))
      $this->warn("Attempt to delete unexistent chunk $chunkTitle !");
    else unset($this->chunkData[$chunkTitle]);
  }

  protected function readChunk($chunkTitle) {
    if (!array_key_exists($chunkTitle, $this->chunkData)) {
      $this->error("Attempt to read unexistent chunk $chunkTitle !");

      return '';
    }

    return $this->chunkData[$chunkTitle];
  }

  protected function currentChunk() {
    return $this->chunkTitle[0];
  }

  protected function readCurrentChunk() {
    return $this->readChunk($this->currentChunk());
  }

  protected function push($string) {
    $this->chunkData[$this->currentChunk()] .= $string;
  }

  # Debugging niceties.
  private $loggers = array(
    'interrupt' => 'error_log',
    'error' => 'error_log',
    'warn' => 'error_log',
    'info' => 'error_log',
  );
  private $disableLoggers = false;

  public function loggingDisable() {
    $this->disableLoggers = true;
  }

  public function loggingEnable() {
    $this->disableLoggers = false;
  }

  private function log($type, $log_data) {
    if ($this->disableLoggers)
      return;

    if (!is_array($log_data))
      $log_data = array($log_data);

    if (function_exists($this->loggers[$type]))
      foreach($log_data as $string)
          $this->loggers[$type](get_class($this) . ": $string");
  }

  protected function interrupt($log_data) {
    $this->log('interrupt', $log_data);

    exit();
  }

  protected function error($log_data) {
    $this->log('error', $log_data);
  }

  protected function warn($log_data) {
    $this->log('warn', $log_data);
  }

  protected function info($log_data) {
    $this->log('info', $log_data);
  }

  public function setLogger($category, $function) {
    if (!array_key_exists($category, $this->loggers))
      return false;

    if ($function === null) {
      $this->loggers[$category] = null;

      return true;
    }

    if (function_exists($function)) {
      $this->loggers[$category] = $function;

      return true;
    }

    return false;
  }

  protected function libXMLErrorsToString($input_string) {
    $xml_errors = libxml_get_errors();
    $input_lines = explode("\n", $input_string);

    $i = 0;
    $data_array = array();
    foreach($xml_errors as $xml_error) {

      $data = 'XML ';
      switch($xml_error->level) {

        case LIBXML_ERR_WARNING:
          $data .= 'Warning';
          break;

        case LIBXML_ERR_ERROR:
          $data .= 'Error';
          break;

        case LIBXML_ERR_FATAL:
          $data .= 'Fatal error';
          break;

        default:
          $data .= 'Unkown error';
      }
      $data .= ': ';

      $data .= trim($xml_error->message);

      $row = $xml_error->line;
      $col = $xml_error->column;
      $inputl = $input_lines[$row - 1];
      $matches = array();
      if (
        ($col > 0)
        && (preg_match_all("/[ \t]/", $inputl, $matches, PREG_OFFSET_CAPTURE, 0))
      ) {
        $npos = 0;
        foreach($matches[0] as $match) {
          $opos = $npos;
          $npos = $match[1];
          if ($npos > $col) {
            $opos++;
            $inputl = "|col$opos>>"
                      . substr($inputl, $opos, $npos - $opos)
                      . "<<col$npos|";

            break;
          }
        }
      }

      $data .= ", line: " . $row
             . ", column: " . $col
             . ", data: " . $inputl;

      $data_array[$i] = $data;
      $i++;
    }

    libxml_clear_errors();

    return $data_array;
  }

}

?>
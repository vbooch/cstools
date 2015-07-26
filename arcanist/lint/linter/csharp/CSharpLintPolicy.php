<?php

abstract class CSharpLintPolicy extends Phobject {

  private $linter;

  public function setArcanistCSharpASTLinter(ArcanistCSharpASTLinter $linter) {
    $this->linter = $linter;
  }

  public abstract function getCode();
  
  public abstract function getName();
  
  public abstract function getDescription();
  
  public abstract function getLintSeverityMap();
  
  public abstract function getLintNameMap();
  
  public abstract function analyzeNode($path, array $ast, array $parents);
  
  public abstract function analyzeToken($path, array $ast, array $parents);
  
  final public function raiseLintAtNodeOrToken(
    $node_or_token,
    $code,
    $desc,
    $original = null,
    $replacement = null) {
    
    return $this->linter->raiseLintAtLine(
      $this->getStartLine($node_or_token),
      $this->getStartColumn($node_or_token),
      $this->getCode().$code,
      $desc,
      $original,
      $replacement);
  }
  
  final public function raiseLintAtLine(
    $line,
    $char,
    $code,
    $desc,
    $original = null,
    $replacement = null) {
    
    return $this->linter->raiseLintAtLine(
      $line,
      $char,
      $this->getCode().$code,
      $desc,
      $original,
      $replacement);
  }

  final public function raiseLintAtPath($code, $desc) {
    return $this->linter->raiseLintAtPath(
      $this->getCode().$code,
      $desc);
  }
  
  protected function findPreviousTokenInHierarchy(array $token, array $parents) {
    if (count($parents) === 0) {
      return null;
    }
    
    $parent = last($parents);
    $children = idx($parent, 'Children', array());
    $children = array_reverse($children);    
    
    $active = false;
    for ($i = 0; $i < count($children); $i++) {
      $child = $children[$i];
      
      if ($child === $token) {
        $active = true;
        continue;
      } else if (!$active) {
        continue;
      }
      
      if ($this->isToken($child)) {
        return $child;
      }
      
      $token = $this->findLastTokenOfNode($child);
      if ($token !== null) {
        return $token;
      }
    }
    
    $copy = $parents;
    array_pop($copy);
    return $this->findPreviousTokenInHierarchy($parent, $copy);
  }
  
  protected function findFirstTokenOfNode(array $node) {
    if (count(idx($node, 'Children', array())) === 0) {
      return null;
    }
    
    foreach (idx($node, 'Children', array()) as $child) {
      if ($this->isToken($child)) {
        return $child;
      } else if ($this->isNode($child)) {
        $result = $this->findFirstTokenOfNode($child);
        if ($result === null) {
          return $result;
        }
      }
    }
    
    return null;
  }
  
  protected function findLastTokenOfNode(array $node) {
    if (count(idx($node, 'Children', array())) === 0) {
      return null;
    }
    
    $children = idx($node, 'Children', array());
    $children = array_reverse($children);
    
    foreach ($children as $child) {
      if ($this->isToken($child)) {
        return $child;
      } else if ($this->isNode($child)) {
        $result = $this->findLastTokenOfNode($child);
        if ($result === null) {
          return $result;
        }
      }
    }
    
    return null;
  }
  
  protected function isToken(array $node_or_token) {
    return idx($node_or_token, 'Type') === 'token';
  }
  
  protected function isNode(array $node_or_token) {
    return idx($node_or_token, 'Type') === 'syntax';
  }
  
  protected function getType(array $node_or_token) {
    return idx($node_or_token, 'ASTType');
  }
  
  protected function getText(array $node_or_token) {
    return idx($node_or_token, 'Text', '');
  }
  
  protected function getValue(array $node_or_token) {
    return idx($node_or_token, 'Value');
  }
  
  protected function getChildren(array $node) {
    if ($this->isNode($node)) {
      return idx($node, 'Children', array());
    } else {
      throw new Exception('getChildren may only be called on nodes');
    } 
  }
  
  protected function getStartLine(array $node_or_token) {
    return 
      idx(
        idx(
          idx(
            $node_or_token,
            'Span',
            array()),
          'Start',
          array()),
        'Line');
  }
  
  protected function getStartColumn(array $node_or_token) {
    return 
      idx(
        idx(
          idx(
            $node_or_token,
            'Span',
            array()),
          'Start',
          array()),
        'Character');
  }
  
  protected function getEndLine(array $node_or_token) {
    return 
      idx(
        idx(
          idx(
            $node_or_token,
            'Span',
            array()),
          'End',
          array()),
        'Line');
  }
  
  protected function getEndColumn(array $node_or_token) {
    return 
      idx(
        idx(
          idx(
            $node_or_token,
            'Span',
            array()),
          'End',
          array()),
        'Character');
  }
  
  protected function getIndentation($token, array $parents) {
    $indentation = '';
    
    for ($i = 0; $i < count($parents); $i++) {
      $parent = $parents[$i];
      $next_parent = idx($parents, $i + 1);
      if ($next_parent === null) {
        $next_parent = $token;
      }
      if ($this->isNode($parent)) {
        if ($next_parent !== null) {
          $children = $this->getChildren($parent);
          $braces = array();
          foreach ($children as $child) {
            if ($this->isToken($child)) {
              if ($this->getValue($child) === '{') {
                array_push($braces, '{');
              } else if ($this->getValue($child) === '}') {
                if (count($braces) > 0 && last($braces) === '{') {
                  array_pop($braces);
                }
              } else if ($this->getValue($child) === '(') {
                array_push($braces, '(');
              } else if ($this->getValue($child) === ')') {
                if (count($braces) > 0 && last($braces) === '(') {
                  array_pop($braces);
                }
              } else if ($this->getValue($child) === '[') {
                array_push($braces, '[');
              } else if ($this->getValue($child) === ']') {
                if (count($braces) > 0 && last($braces) === '[') {
                  array_pop($braces);
                }
              } else if ($this->getValue($child) === '<') {
                array_push($braces, '<');
              } else if ($this->getValue($child) === '>') {
                if (count($braces) > 0 && last($braces) === '<') {
                  array_pop($braces);
                }
              }
            }
            
            if ($child === $next_parent) {
              if ($this->isToken($child)) {
                if ($this->getValue($child) === '{' ||
                    $this->getValue($child) === '(' ||
                    $this->getValue($child) === '<' ||
                    $this->getValue($child) === '[') {
                  // Do not include the brace itself when
                  // considering indentation.
                  array_pop($braces);
                }
              }
            
              for ($a = 0; $a < count($braces); $a++) {
                $indentation .= '    ';
              }
              
              break;
            }
          }
        }
      }
    }
    
    return $indentation;
  }
  
  protected function getWhitespaceBeforeNode($node, array $parents, $previous = null) {
    if (count($parents) === 0) {
      return '';
    }
    
    if ($previous === null) {
      // Don't search again if the callee already has the previous token.
      $previous = $this->findPreviousTokenInHierarchy($node, $parents);
    }
    
    $parent_offset = null;
    $previous_offset = idx($previous, 'SpanStart');
    $previous_length = strlen(idx($previous, 'TrimmedText'));
    $node_offset = idx($node, 'SpanStart');
    $idx = count($parents);
    while ($parent_offset === null || $previous_offset < $parent_offset) {
      $idx--;
      $parent = $parents[$idx];
      $parent_offset = idx($parent, 'SpanStart');
    }
    
    $previous_end = ($previous_offset + $previous_length) - $parent_offset;
    $node_start = $node_offset - $parent_offset;
    
    $substr = substr(
      idx($parent, 'TrimmedText'),
      $previous_end,
      $node_start - $previous_end);
      
    return $substr;
  }
  
}
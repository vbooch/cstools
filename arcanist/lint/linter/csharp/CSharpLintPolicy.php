<?php

abstract class CSharpLintPolicy extends Phobject {

  private $linter;
  private $globalOffset;

  public function setArcanistCSharpASTLinter(ArcanistCSharpASTLinter $linter) {
    $this->linter = $linter;
  }
  
  public function setGlobalOffset($offset) {
    $this->globalOffset = $offset;
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

  final public function raiseLintAtOffset(
    $offset,
    $code,
    $desc,
    $original = null,
    $replacement = null) {
    
    return $this->linter->raiseLintAtOffset(
      $offset + $this->globalOffset,
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
  
  protected function findNextTokenInHierarchy(array $token, array $parents) {
    if (count($parents) === 0) {
      return array(null, array());
    }
    
    $parent = last($parents);
    $children = idx($parent, 'Children', array());
    
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
        return array($child, $parents);
      }
      
      list($token_candidate, $token_parents) =
        $this->findFirstTokenOfNode($child, true, $parents);
      if ($token_candidate !== null) {
        return array($token_candidate, $token_parents);
      }
    }
    
    $copy = $parents;
    array_pop($copy);
    return $this->findNextTokenInHierarchy($parent, $copy);
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
      
      $token_candidate = 
        $this->findLastTokenOfNode($child);
      if ($token_candidate !== null) {
        return $token_candidate;
      }
    }
    
    $copy = $parents;
    array_pop($copy);
    return $this->findPreviousTokenInHierarchy($parent, $copy);
  }
  
  protected function findFirstTokenOfNode(
    array $node,
    $include_parents = false,
    $parents = array()) {
    
    if (count(idx($node, 'Children', array())) === 0) {
      if ($include_parents) {
        return array(null, array());
      } else {
        return null;
      }
    }
    
    array_push($parents, $node);
    foreach (idx($node, 'Children', array()) as $child) {
      if ($this->isToken($child)) {
        if ($include_parents) {
          return array($child, $parents);
        } else {
          return $child;
        }
      } else if ($this->isNode($child)) {
        list($result, $result_parents) = $this->findFirstTokenOfNode(
          $child,
          true,
          $parents);
        if ($result !== null) {
          if ($include_parents) {
            return array($result, $result_parents);
          } else {
            return $result;
          }
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
        if ($result !== null) {
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
  
  protected function getOffset(array $node_or_token) {
    return idx($node_or_token, 'SpanStart');
  }
  
  protected function getLeadingTrivia(array $node_or_token) {
    return idx($node_or_token, 'LeadingTrivia');
  }
  
  protected function getTrailingTrivia(array $node_or_token) {
    return idx($node_or_token, 'TrailingTrivia');
  }
  
  protected function getIndentation($token, array $parents, $is_trivia_before_token = false) {
    if (count($parents) === 0) {
      return '';
    }
    
    $parent = head($parents);
    return $this->getIndentationFromParent(
      0,
      $parent,
      $token,
      $parents,
      $is_trivia_before_token,
      array(),
      null);
  }
  
  protected function getIndentationFromParent(
    $index,
    $parent,
    $token,
    $parents,
    $is_trivia_before_token,
    $global_braces,
    $last_vardec_line) {
    
    $next_parent = idx($parents, $index + 1);
    if ($next_parent === null) {
      $next_parent = $token;
    }
    foreach ($this->getChildren($parent) as $child) {
      $did_indent = false;
      
      // Before next parent / token.
      if ($this->isNode($child)) {
        if ($this->getType($child) === 'CaseSwitchLabelSyntax' ||
          $this->getType($child) === 'DefaultSwitchLabelSyntax') {
          if ($this->isNode(last($global_braces)) &&
            ($this->getType(last($global_braces)) === 'CaseSwitchLabelSyntax' ||
            $this->getType(last($global_braces)) === 'DefaultSwitchLabelSyntax')) {
            array_pop($global_braces);
          }
          array_push($global_braces, $child);
          $did_indent = true;
        }
        
        if ($this->getType($child) === 'InvocationExpressionSyntax' ||
            $this->getType($child) === 'MemberAccessExpressionSyntax' ||
            $this->getType($child) === 'ObjectCreationExpressionSyntax') {
          if (count($global_braces) > 0) {
            $last_gb = last($global_braces);
            if ($this->isNode($last_gb)) {
              if ($this->getType($last_gb) === 'VariableDeclarationSyntax' ||
                  $this->getType($last_gb) === 'AssignmentExpressionSyntax') {
                if ($this->getStartLine($child) === $this->getStartLine($last_gb)) {
                  // TODO This is not the right way to find the assignment operator,
                  // to do this properly we need to traverse the tree from here, find
                  // the = token, and compare it's offset with the current child.  This
                  // means we need a function for searching for a specific token.
                  //print_r("last_gb text trimmed: ".json_encode($this->getText($last_gb))."\n");
                  $assignment = strpos(idx($last_gb, 'TrimmedText'), '=');
                  if ($assignment !== false) {
                    //print_r("last_gb text: ".json_encode($this->getText($last_gb))."\n");
                    //print_r("last_gb offset: ".json_encode($this->getOffset($last_gb))."\n");
                    //print_r("last_gb offset + assignment: ".json_encode($this->getOffset($last_gb) + $assignment)."\n");
                    //print_r("child text: ".json_encode($this->getText($child))."\n");
                    //print_r("child offset: ".json_encode($this->getOffset($child))."\n");
                    if ($this->getOffset($child) > $this->getOffset($last_gb) + $assignment) {
                      // Cancel the indentation of the variable declaration 
                      // since we have an overridding call that encapsulates
                      // other indentation.
                      //print_r("popped\n");
                      array_pop($global_braces);
                    }
                  }
                }
              }
            }
          }
        }
        
        if ($this->getType($child) === 'MemberAccessExpressionSyntax') {
          if ($this->getStartLine($child) < $this->getStartLine($token)) {
            $last_gb = last($global_braces);
            if (!$this->isNode($last_gb) ||
              $this->getType($last_gb) !== 'MemberAccessExpressionSyntax') {
              array_push($global_braces, $child);
            }
          }
        }
        
        if ($this->getType($child) === 'VariableDeclarationSyntax') {
          if ($this->getStartLine($child) < $this->getStartLine($token)) {
            $last_gb = last($global_braces);
            if (!$this->isNode($last_gb) ||
              $this->getType($last_gb) !== 'VariableDeclarationSyntax') {
              array_push($global_braces, $child);
            }
          }
        }
        
        if ($this->getType($child) === 'AssignmentExpressionSyntax') {
          if ($this->getStartLine($child) < $this->getStartLine($token)) {
            $last_gb = last($global_braces);
            if (!$this->isNode($last_gb) ||
              $this->getType($last_gb) !== 'AssignmentExpressionSyntax') {
              array_push($global_braces, $child);
            }
          }
        }
      } else if ($this->isToken($child)) {
        if ($this->getValue($child) === '{') {
          array_push($global_braces, $child);
          $did_indent = true;
        } else if ($this->getValue($child) === '(') {
          array_push($global_braces, $child);
          $did_indent = true;
        } else if ($this->getValue($child) === '[') {
          if ($this->getType($parent) !== 'AttributeListSyntax') {
            array_push($global_braces, $child);
            $did_indent = true;
          }
        } else if ($this->getValue($child) === '<') {
          array_push($global_braces, $child);
          $did_indent = true;
        } else if ($this->getValue($child) === '}') {
          if ($child !== $token || !$is_trivia_before_token) {
            if (count($global_braces) > 0 && 
              $this->isToken(last($global_braces)) &&
              $this->getValue(last($global_braces)) === '{') {
              array_pop($global_braces);
            }
          }
        } else if ($this->getValue($child) === ')') {
          if ($child !== $token || !$is_trivia_before_token) {
            if (count($global_braces) > 0 && 
              $this->isToken(last($global_braces)) &&
              $this->getValue(last($global_braces)) === '(') {
              array_pop($global_braces);
            }
          }
        } else if ($this->getValue($child) === ']') {
          if ($this->getType($parent) !== 'AttributeListSyntax') {
            if ($child !== $token || !$is_trivia_before_token) {
              if (count($global_braces) > 0 && 
                $this->isToken(last($global_braces)) &&
                $this->getValue(last($global_braces)) === '[') {
                array_pop($global_braces);
              }
            }
          }
        } else if ($this->getValue($child) === '>') {
          if ($child !== $token || !$is_trivia_before_token) {
            if (count($global_braces) > 0 && 
              $this->isToken(last($global_braces)) &&
              $this->getValue(last($global_braces)) === '<') {
              array_pop($global_braces);
            }
          }
        }
      }
      
      // Traverse down the tree if the next parent is not the token.
      if ($child === $next_parent && $next_parent !== $token) {
        return $this->getIndentationFromParent(
          $index + 1,
          $child,
          $token,
          $parents,
          $is_trivia_before_token,
          $global_braces,
          $last_vardec_line);
      } else if ($child === $token) {
        if ($did_indent == true) {
          // Do not include the child causing indentation
          // when considering indentation.
          array_pop($global_braces);
        }
      
        // If our parent is a case or default statement, then do not
        // cause the 'case' or 'default' statements to be indented.
        if ($this->getType($parent) === 'CaseSwitchLabelSyntax' ||
          $this->getType($parent) === 'DefaultSwitchLabelSyntax') {
          array_pop($global_braces);
        }
      
        $indentation = '';
        for ($a = 0; $a < count($global_braces); $a++) {
          $indentation .= '    ';
        }
        return $indentation;
      }
      
      // After the node / token.
      if ($this->isNode($child)) {
        if ($this->getType($child) === 'CaseSwitchLabelSyntax' ||
          $this->getType($child) === 'DefaultSwitchLabelSyntax') {
          // do nothing here
        } else if (last($global_braces) === $child) {
          array_pop($global_braces);
        }
      } else if ($this->isToken($child)) {
      }
    }
  }
  
  protected function getWhitespaceWithOffsetBeforeToken($token, array $parents, $previous = null) {
    $whitespace_offset = $this->getOffset($token);
    $whitespace = '';
    $trivias = idx($token, 'LeadingTrivia', array());
    $trivias = array_reverse($trivias);
    $stop = false;
    foreach ($trivias as $trivia) {
      $kind = idx($trivia, 'Kind');
      switch ($kind) {
        case 'WhitespaceTrivia':
        case 'EndOfLineTrivia':
          $whitespace = $this->getText($trivia).$whitespace;
          $whitespace_offset = $this->getOffset($trivia);
          break;
        default:
          $stop = true;
          break;
      }
      
      if ($stop) {
        break;
      }
    }
    
    // Now also check the previous token in the hierarchy and pull it's
    // trailing trivia.
    if ($previous === null) {
      $previous = $this->findPreviousTokenInHierarchy($token, $parents);
    }
    if ($previous !== null) {
      $trivias = idx($previous, 'TrailingTrivia', array());
      $trivias = array_reverse($trivias);
      $stop = false;
      foreach ($trivias as $trivia) {
        $kind = idx($trivia, 'Kind');
        switch ($kind) {
          case 'WhitespaceTrivia':
          case 'EndOfLineTrivia':
            $whitespace = $this->getText($trivia).$whitespace;
            $whitespace_offset = $this->getOffset($trivia);
            break;
          default:
            $stop = true;
            break;
        }
        
        if ($stop) {
          break;
        }
      }
    }
    
    return array($whitespace, $whitespace_offset);
  }
  
  protected function getWhitespaceWithOffsetBeforeTrivia(
    $token,
    $target_trivia,
    $trivias,
    $is_leading,
    array $parents,
    $previous = null) {
    
    $whitespace_offset = $this->getOffset($target_trivia);
    $whitespace = '';
    
    $trivias = array_reverse($trivias);
    
    $stop = false;
    $active = false;
    foreach ($trivias as $trivia) {
      if ($trivia === $target_trivia) {
        $active = true;
        continue;
      } else if (!$active) {
        continue;
      }
      
      $kind = idx($trivia, 'Kind');
      switch ($kind) {
        case 'WhitespaceTrivia':
        case 'EndOfLineTrivia':
          $whitespace = $this->getText($trivia).$whitespace;
          $whitespace_offset = $this->getOffset($trivia);
          break;
        default:
          $stop = true;
          break;
      }
      
      if ($stop) {
        break;
      }
    }
    
    if ($is_leading) {
      // Now also check the previous token in the hierarchy and pull it's
      // trailing trivia.
      if ($previous === null) {
        $previous = $this->findPreviousTokenInHierarchy($token, $parents);
      }
      if ($previous !== null) {
        $trivias = idx($previous, 'TrailingTrivia', array());
        $trivias = array_reverse($trivias);
        $stop = false;
        foreach ($trivias as $trivia) {
          $kind = idx($trivia, 'Kind');
          switch ($kind) {
            case 'WhitespaceTrivia':
            case 'EndOfLineTrivia':
              $whitespace = $this->getText($trivia).$whitespace;
              $whitespace_offset = $this->getOffset($trivia);
              break;
            default:
              $stop = true;
              break;
          }
          
          if ($stop) {
            break;
          }
        }
      }
    }
    
    return array($whitespace, $whitespace_offset);
  }
  
}
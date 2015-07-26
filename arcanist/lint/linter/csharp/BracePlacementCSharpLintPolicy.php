<?php

final class BracePlacementCSharpLintPolicy extends CSharpLintPolicy {

  const BRACE_MUST_BE_ON_NEWLINE = '1';

  public function getCode() {
    return 'BRACE';
  }
  
  public function getName() {
    return pht('Brace Placement');
  }
  
  public function getDescription() {
    return pht(
      'Ensures braces are placed on a new line when starting '.
      'a code or initializer block');
  }
  
  public function getLintSeverityMap() {
    return array(
      self::BRACE_MUST_BE_ON_NEWLINE => ArcanistLintSeverity::SEVERITY_ERROR,
    );
  }
  
  public function getLintNameMap() {
    return array(
      self::BRACE_MUST_BE_ON_NEWLINE => pht('Brace must be on newline'),
    );
  }
  
  public function analyzeNode($path, array $node, array $parents) {
  }
  
  public function analyzeToken($path, array $token, array $parents) {
    if (idx($token, 'ASTType') === 'SyntaxToken') {
      if ($this->getValue($token) === '{') {
        // If the ending brace is on the same line, and there are no
        // opening braces in between, then we permit the brace on the
        // same line.
        $active = false;
        $has_opening_brace = false;
        $has_closing_brace = false;
        foreach ($this->getChildren(last($parents)) as $child) {
          if ($child === $token) {
            $active = true;
            continue;
          }
          
          if (!$active) {
            continue;
          }
          
          if ($this->isToken($child)) {
            if (idx($child, 'ASTType') === 'SyntaxToken') {
              if ($this->getValue($child) === '{') {
                $has_opening_brace = true;
                break;
              } else if ($this->getValue($child) === '}') {
                if ($this->getStartLine($token) === 
                  $this->getStartLine($child)) {
                  $has_closing_brace = true;
                  break;
                }
              }
            }
          }
        }
        
        if (!$active) {
          throw new Exception(pht(
            'Invalid AST traversal; expected to find token as child'));
        }
        
        if ($has_closing_brace && !$has_opening_brace) {
          return;
        }
      
        $previous = $this->findPreviousTokenInHierarchy($token, $parents);
        if ($previous !== null) {
          $my_line = $this->getStartLine($token);
          $prev_line = $this->getEndLine($previous);
          if ($my_line === $prev_line) {
            list($whitespace, $whitespace_offset) = 
              $this->getWhitespaceWithOffsetBeforeToken(
                $token,
                $parents,
                $previous);
            $this->raiseLintAtOffset(
              $whitespace_offset,
              self::BRACE_MUST_BE_ON_NEWLINE,
              'Opening braces must be placed on a '.
              'newline when the closing brace is '.
              'not on the same line.',
              $whitespace.$this->getText($token),
              "\n".
              $this->getIndentation($token, $parents).
              $this->getText($token));
          }
        }
      }
    }
  }
  
}
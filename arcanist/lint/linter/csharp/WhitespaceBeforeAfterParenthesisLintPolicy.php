<?php

final class WhitespaceBeforeAfterParenthesisLintPolicy
  extends CSharpLintPolicy {

  const NO_WHITESPACE_BEFORE_PARENTHESIS = '1';
  const NO_WHITESPACE_AFTER_PARENTHESIS = '2';

  public function getCode() {
    return 'PAREN';
  }
  
  public function getName() {
    return pht('Whitespace before / after Parenthesis');
  }
  
  public function getDescription() {
    return pht(
      'Ensures there are no whitespace tokens before or after '.
      'parenthesis, for method declarations, invocations or '.
      'type casts.');
  }
  
  public function getLintSeverityMap() {
    return array(
      self::NO_WHITESPACE_BEFORE_PARENTHESIS => 
        ArcanistLintSeverity::SEVERITY_ERROR,
      self::NO_WHITESPACE_AFTER_PARENTHESIS => 
        ArcanistLintSeverity::SEVERITY_ERROR,
    );
  }
  
  public function getLintNameMap() {
    return array(
      self::NO_WHITESPACE_BEFORE_PARENTHESIS =>
        pht('Whitespace not permitted before parenthesis'),
      self::NO_WHITESPACE_AFTER_PARENTHESIS =>
        pht('Whitespace not permitted after parenthesis'),
    );
  }
  
  public function analyzeNode($path, array $node, array $parents) {
  }
  
  public function analyzeToken($path, array $token, array $parents) {
    if (count($parents) >= 1) {
      $parent = last($parents);
      if ($this->getType($parent) !== 'ParameterListSyntax' &&
        $this->getType($parent) !== 'ArgumentListSyntax') {
        // This rule only applies for parameter and argument lists.
        return;
      }
      
      if ($this->getType($parent) === 'ParameterListSyntax') {
        if (count($parents) >= 2) {
          $parent_of_parent = $parents[count($parents) - 2];
          if ($this->getType($parent_of_parent) ===
            'ParenthesizedLambdaExpressionSyntax') {
            // Do not apply this rule if the parameter list is part
            // of a lambda.
            return;
          }
        }
      }
    }
    
    if (idx($token, 'ASTType') === 'SyntaxToken') {
      if ($this->getValue($token) === '(') {
        $previous = $this->findPreviousTokenInHierarchy($token, $parents);
        if ($previous !== null) {
          $my_line = $this->getStartLine($token);
          $my_column = $this->getStartColumn($token);
          $prev_line = $this->getEndLine($previous);
          $prev_column = $this->getEndColumn($previous);
          
          if ($prev_line < $my_line) {
            // The method parenthesis is on the line after the
            // identifier.  We need to remove the newline and
            // indent the node after the parenthesis.  If the
            // node after the parenthesis is the closing
            // parenthesis, then instead move both parenthesis'
            // back onto the same line.
            $empty_block = false;
            list($next, $next_parents) = 
              $this->findNextTokenInHierarchy($token, $parents);
            if ($this->getType($next) === 'SyntaxToken') {
              if ($this->getValue($next) === ')') {
                $empty_block = true;
              }
            }
            
            list($whitespace_before_open, $ws_before_open_offset) = 
              $this->getWhitespaceWithOffsetBeforeToken(
                $token,
                $parents,
                $previous);
            list($whitespace_before_next, $ws_before_next_offset) = 
              $this->getWhitespaceWithOffsetBeforeToken(
                $next,
                $next_parents,
                $token);
            if ($empty_block) {
              $this->raiseLintAtOffset(
                $ws_before_open_offset,
                self::NO_WHITESPACE_BEFORE_PARENTHESIS,
                'Whitespace is not permitted before '.
                'a parenthesis.',
                $whitespace_before_open.
                $this->getText($token).
                $whitespace_before_next.
                $this->getText($next),
                $this->getText($token).$this->getText($next));
            } else {
              $this->raiseLintAtOffset(
                $ws_before_open_offset,
                self::NO_WHITESPACE_BEFORE_PARENTHESIS,
                'Whitespace is not permitted before '.
                'a parenthesis.',
                $whitespace_before_open.
                $this->getText($token).
                $whitespace_before_next.
                $this->getText($next),
                $this->getText($token)."\n".
                $this->getIndentation($next, $next_parents).
                $this->getText($next));
            }
          } else if ($prev_line === $my_line) {
            if ($prev_column < $my_column) {
              // There is whitespace between the parenthesis
              // and the previous token, so we need to remove it.
              list($whitespace, $whitespace_offset) = 
                $this->getWhitespaceWithOffsetBeforeToken(
                  $token,
                  $parents,
                  $previous);
              $this->raiseLintAtOffset(
                $whitespace_offset,
                self::NO_WHITESPACE_BEFORE_PARENTHESIS,
                'Whitespace is not permitted before '.
                'a parenthesis.',
                $whitespace.$this->getText($token),
                $this->getText($token));
            }
          }
        }
      }
    }
  }
  
}
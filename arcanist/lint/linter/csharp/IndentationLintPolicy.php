<?php

final class IndentationLintPolicy
  extends CSharpLintPolicy {

  const INDENTATION_IS_INCORRECT = '1';

  public function getCode() {
    return 'INDENT';
  }
  
  public function getName() {
    return pht('Indentation');
  }
  
  public function getDescription() {
    return pht(
      'Ensures that all code is indented properly.');
  }
  
  public function getLintSeverityMap() {
    return array(
      self::INDENTATION_IS_INCORRECT => 
        ArcanistLintSeverity::SEVERITY_ERROR,
    );
  }
  
  public function getLintNameMap() {
    return array(
      self::INDENTATION_IS_INCORRECT =>
        pht('Code is not indented correctly'),
    );
  }
  
  public function analyzeNode($path, array $node, array $parents) {
  }
  
  public function analyzeToken($path, array $token, array $parents) {
    list($whitespace, $whitespace_offset) = 
      $this->getWhitespaceWithOffsetBeforeToken(
      $token,
      $parents);
    $last_newline = strrpos($whitespace, "\n");
    if ($last_newline !== false) {
      $current_indentation = substr($whitespace, $last_newline + 1);
      $indentation = $this->getIndentation($token, $parents);
      if (strlen($current_indentation) !== strlen($indentation)) {
        $this->raiseLintAtOffset(
          $this->getOffset($token) - strlen($current_indentation),
          self::INDENTATION_IS_INCORRECT,
          'This token is not indented correctly.',
          $current_indentation,
          $indentation);
      }
    }
    
    // Also analyze any comment trivia to ensure they are indented
    // correctly.
    $trivias = $this->getLeadingTrivia($token);
    foreach ($trivias as $trivia) {
      $this->analyzeTrivia($path, $token, $trivia, $trivias, true, $parents);
    }
    $trivias = $this->getTrailingTrivia($token);
    foreach ($trivias as $trivia) {
      $this->analyzeTrivia($path, $token, $trivia, $trivias, false, $parents);
    }
  }
  
  private function analyzeTrivia(
    $path,
    array $token,
    array $trivia,
    array $trivias,
    $is_leading,
    array $parents) {
    
    $indentation = $this->getIndentation($token, $parents, $is_leading);

    switch (idx($trivia, 'Kind')) {
      case 'SingleLineDocumentationCommentTrivia':
      case 'MultiLineCommentTrivia':
      case 'SingleLineCommentTrivia':
        list($whitespace, $offset) = 
          $this->getWhitespaceWithOffsetBeforeTrivia(
            $token,
            $trivia,
            $trivias,
            $is_leading,
            $parents);
        $combined = $whitespace.$this->getText($trivia);
        $lines = phutil_split_lines($combined, true);
        
        foreach ($lines as $line) {
          $trimmed_line = ltrim($line);
          if (strlen($trimmed_line) === 0) {
            // Ignore blank lines.
            $offset += strlen($line);
            continue;
          }
          
          $npos = strpos($combined, "\n");
          if ($npos === false || $npos > strpos($combined, $line)) {
            // This trivia does not have a newline before it.
            continue;
          }
          
          $indentation_chars = strlen($line) - strlen($trimmed_line);
          if ($indentation_chars !== strlen($indentation)) {
            $current_indentation = substr($line, 0, $indentation_chars);
            $this->raiseLintAtOffset(
              $offset,
              self::INDENTATION_IS_INCORRECT,
              'This trivia is not indented correctly.',
              $current_indentation,
              $indentation);
          }
          $offset += strlen($line);
        }
        break;
    }
  }
  
}
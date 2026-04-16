<?php

/**
 * Toon format parser.
 *
 * Toon is a lightweight indentation-based data format used to define post meta
 * field groups. A .toon file contains a list of items (lines starting with "- ")
 * where each item can have key: value pairs, nested objects (key:), typed lists
 * (key[N]:), and columnar arrays (key[N]{col1,col2}:).
 */

function parse_toon_file($filepath)
{
  $raw = file_get_contents($filepath);
  if ($raw === false) return false;

  $lines = explode("\n", $raw);
  $pos   = 0;
  $len   = count($lines);

  toon_skip_empty($lines, $pos, $len);
  if ($pos >= $len) return [];

  if (preg_match('/^\[(\d+)\]:/', trim($lines[$pos]))) {
    $pos++;
  }

  return toon_parse_list($lines, $pos, $len, 0);
}

function toon_skip_empty(&$lines, &$pos, $len)
{
  while ($pos < $len && trim($lines[$pos]) === '') $pos++;
}

function toon_indent($line)
{
  return strlen($line) - strlen(ltrim($line, ' '));
}

function toon_parse_list(&$lines, &$pos, $len, $min_indent)
{
  $result = [];

  while ($pos < $len) {
    toon_skip_empty($lines, $pos, $len);
    if ($pos >= $len) break;

    $indent  = toon_indent($lines[$pos]);
    $trimmed = ltrim($lines[$pos]);

    if ($indent < $min_indent || ($trimmed !== '-' && !str_starts_with($trimmed, '- '))) break;

    $pos++;
    $content           = str_starts_with($trimmed, '- ') ? substr($trimmed, 2) : '';
    $item_props_indent = $indent + 2;

    $obj = [];
    if ($content !== '') {
      $obj = toon_parse_kv($content, $lines, $pos, $len, $item_props_indent, $obj);
    }

    while ($pos < $len) {
      toon_skip_empty($lines, $pos, $len);
      if ($pos >= $len) break;

      $cur_indent = toon_indent($lines[$pos]);
      if ($cur_indent < $item_props_indent) break;

      $kv_line = ltrim($lines[$pos]);
      $pos++;
      $obj = toon_parse_kv($kv_line, $lines, $pos, $len, $cur_indent + 2, $obj);
    }

    $result[] = $obj;
  }

  return $result;
}

function toon_parse_csv_row($line)
{
  $fields = [];
  $len    = strlen($line);
  $i      = 0;

  while ($i < $len) {
    if ($line[$i] === '"') {
      // Quoted field — find closing quote
      $i++;
      $field = '';
      while ($i < $len) {
        if ($line[$i] === '"' && isset($line[$i + 1]) && $line[$i + 1] === '"') {
          $field .= '"';
          $i += 2;
        } elseif ($line[$i] === '"') {
          $i++;
          break;
        } else {
          $field .= $line[$i++];
        }
      }
      $fields[] = $field;
      if ($i < $len && $line[$i] === ',') {
        $i++;
      }
    } else {
      // Unquoted field — read until next comma
      $end = strpos($line, ',', $i);
      if ($end === false) {
        $fields[] = substr($line, $i);
        break;
      }
      $fields[] = substr($line, $i, $end - $i);
      $i        = $end + 1;
    }
  }

  return $fields;
}

function toon_parse_kv($line, &$lines, &$pos, $len, $children_indent, $obj)
{
  // Columnar array: key[N]{col1,col2,...}:
  if (preg_match('/^(\w+)\[(\d+)\]\{([^}]+)\}:\s*$/', $line, $m)) {
    $key   = $m[1];
    $count = (int) $m[2];
    $cols  = array_map('trim', explode(',', $m[3]));
    $rows  = [];

    for ($i = 0; $i < $count && $pos < $len; $i++) {
      toon_skip_empty($lines, $pos, $len);
      if ($pos >= $len) break;
      $row_line = trim($lines[$pos++]);
      $values   = toon_parse_csv_row($row_line);
      $row      = [];
      foreach ($cols as $ci => $col) {
        $row[$col] = $values[$ci] ?? '';
      }
      $rows[] = $row;
    }

    $obj[$key] = $rows;
    return $obj;
  }

  // List: key[N]:
  if (preg_match('/^(\w+)\[(\d+)\]:\s*$/', $line, $m)) {
    $obj[$m[1]] = toon_parse_list($lines, $pos, $len, $children_indent);
    return $obj;
  }

  // Inline array: key[N]: value
  if (preg_match('/^(\w+)\[(\d+)\]:\s*(.+)$/', $line, $m)) {
    $obj[$m[1]] = [toon_scalar($m[3])];
    return $obj;
  }

  // Scalar: key: value
  if (preg_match('/^(\w+):\s*(.+)$/', $line, $m)) {
    $obj[$m[1]] = toon_scalar($m[2]);
    return $obj;
  }

  // Nested object: key:
  if (preg_match('/^(\w+):\s*$/', $line, $m)) {
    $key    = $m[1];
    $nested = [];

    while ($pos < $len) {
      toon_skip_empty($lines, $pos, $len);
      if ($pos >= $len) break;

      $cur_indent = toon_indent($lines[$pos]);
      if ($cur_indent < $children_indent) break;

      $kv_line = ltrim($lines[$pos]);
      $pos++;
      $nested = toon_parse_kv($kv_line, $lines, $pos, $len, $cur_indent + 2, $nested);
    }

    $obj[$key] = $nested;
    return $obj;
  }

  return $obj;
}

function toon_scalar($value)
{
  $v = trim($value);
  if ($v === '""') return '';
  if (ctype_digit($v)) return (int) $v;
  return $v;
}

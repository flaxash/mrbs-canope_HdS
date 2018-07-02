<?php

// $Id: functions.inc 2825 2014-03-08 10:02:25Z cimorrison $


// Set the default timezone.   Caters for PHP servers that don't
// have date_default_timezone_set()
function mrbs_default_timezone_set($timezone)
{
  global $area_defaults;
  
  if (!isset($timezone))
  {
    // to prevent people running into DST problems
    $message = 'Configuration error: $timezone has not been set.';
    trigger_error($message, E_USER_WARNING);
    fatal_error(FALSE, $message);
  }
  else
  {
    if (function_exists("date_default_timezone_set"))
    {
      // We try and set the timezone to $timezone, which is the timezone setting
      // for the area.   If that fails we try the default for new areas.   And if
      // that fails we try the server timezone.
      if (!date_default_timezone_set($timezone))
      {
        $fallback_timezone = $area_defaults['timezone'];
        if (!date_default_timezone_set($fallback_timezone))
        {
          // date_default_timezone_get() was introduced at the same time as
          // date_default_timezone_set(), so it will exist
          $fallback_timezone = date_default_timezone_get();
          if (!date_default_timezone_set($fallback_timezone))
          {
            $message = "Could not set any timezone";
            trigger_error($message, E_USER_WARNING);
            fatal_error(TRUE, $message);
          }
        }
        trigger_error("Could not set timezone to '$timezone'; using '$fallback_timezone' instead", E_USER_WARNING);
      }
    }
    else
    {
      putenv("TZ=$timezone");
    }
  }
}


// Get the default timezone.  Caters for PHP servers that don't
// have date_default_timezone_get()
function mrbs_default_timezone_get()
{
  if (function_exists("date_default_timezone_get"))
  {
    return date_default_timezone_get();
  }
  else
  {
    return getenv('TZ');
  }
}


// Deal with $private_xxxx overrides.  Simplifies
// logic related to private bookings.
global $private_override;
if ($private_override == "private" )
{
  $private_mandatory=TRUE;
  $private_default=TRUE;
}
elseif ($private_override == "public" )
{
  $private_mandatory=TRUE;
  $private_default=FALSE;
}

$done_header = FALSE;


// Outputs the HTTP headers
function http_headers()
{
  header("Content-Type: text/html; charset=" . get_charset());
  // We want to prevent IE8 offering "Compatability View" as that really
  // messes up MRBS, especially on the Report page
  header("X-UA-Compatible: IE=Edge");
  header("Pragma: no-cache");                          // HTTP 1.0
  header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");    // Date in the pastt
}


// Prints a very simple header.  This may be necessary on occasions, such as
// during a database upgrade, when some of the features that the normal
// header uses are not yet available.
function print_simple_header()
{
  global $done_header;
 
  if ($done_header)
  {
    return;
  } 
  
  http_headers();
  echo DOCTYPE;
 ?>

<html>
  <head>
    <?php
      require_once "style.inc";
    ?>
    <title><?php echo get_vocab("mrbs") ?></title>
  </head>
  <body>
  <?php
  $done_header = TRUE; 
}


// Print the page header
function print_header($day, $month, $year, $area, $room)
{
  global $theme, $done_header;
  
  if ($done_header)
  {
    return;
  }
  
  // Load the print_theme_header function appropriate to the theme.    If there
  // isn't one then fall back to the default header.
  if (file_exists("Themes/$theme/header.inc"))
  {
    include_once "Themes/$theme/header.inc";
  }
  if (!function_exists("print_theme_header"))
  {
    require_once "Themes/default/header.inc";
  }
  // Now go and do it
  print_theme_header($day, $month, $year, $area, $room);
  $done_header = TRUE;
}



// Print the standard footer, currently very simple.  Pass $and_exit as
// TRUE to exit afterwards
function print_footer($and_exit)
{
  global $theme;
  if (file_exists("Themes/$theme/footer.inc"))
  {
    include_once "Themes/$theme/footer.inc";
  }
  if (function_exists("print_theme_footer"))
  {
    print_theme_footer();
  }
  else {
?>
</body>
</html>
<?php
  }
  if ($and_exit)
  {
    exit(0);
  }
}


// Format a timestamp in RFC 1123 format, for HTTP headers
//
// e.g. Wed, 28 Jul 2010 12:43:58 GMT
function rfc1123_date($timestamp)
{
  return gmdate("D, d M Y G:i:s \\G\\M\\T",$timestamp);
}


// A little helper function to send an "Expires" header. Just one
// parameter, the number of seconds in the future to set the expiry
// (We also send a couple of extra headers as the "Expires" header alone
// does not always result in caching.)
function expires_header($seconds)
{
  header("Expires: ".rfc1123_date(time()+$seconds));
  header("Pragma: cache");
  header("Cache-Control: max-age=$seconds");
}

// Converts a duration of $dur seconds into a duration of
// $dur $units
function toTimeString(&$dur, &$units, $translate=TRUE)
{
  if (abs($dur) >= 60)
  {
    $dur /= 60;

    if (abs($dur) >= 60)
    {
      $dur /= 60;

      if((abs($dur) >= 24) && ($dur % 24 == 0))
      {
        $dur /= 24;

        if((abs($dur) >= 7) && ($dur % 7 == 0))
        {
          $dur /= 7;

          if ((abs($dur) >= 52) && ($dur % 52 == 0))
          {
            $dur  /= 52;
            $units = "years";
          }
          else
          {
            $units = "weeks";
          }
        }
        else
        {
          $units = "days";
        }
      }
      else
      {
        $units = "hours";
      }
    }
    else
    {
      $units = "minutes";
    }
  }
  else
  {
    $units = "seconds";
  }
  
  // Limit any floating point values to three decimal places
  if (is_float($dur))
  {
    $dur = sprintf('%.3f', $dur);
    $dur = rtrim($dur, '0');  // removes trailing zeros
  }
  
  // Translate into local language if required
  if ($translate)
  {
    $units = get_vocab($units);
  }
}


// Converts a time period of $units into seconds, when it is originally
// expressed in $dur_units.   (Almost the inverse of toTimeString(),
// but note that toTimeString() can do language translation)
function fromTimeString(&$units, $dur_units)
{
  if (!isset($units) || !isset($dur_units))
  {
    return;
  }
  
  switch($dur_units)
  {
    case "years":
      $units *= 52;
    case "weeks":
      $units *= 7;
    case "days":
      $units *= 24;
    case "hours":
      $units *= 60;
    case "periods":
    case "minutes":
      $units *= 60;
    case "seconds":
      break;
  }
  $units = (int) $units;
}


function toPeriodString($start_period, &$dur, &$units, $translate=TRUE)
{
  global $periods;

  $max_periods = count($periods);
  $dur /= 60;  // duration now in minutes
  $days = $dur / MINUTES_PER_DAY;
  $remainder = $dur % MINUTES_PER_DAY;
  // strip out any gap between the end of the last period on one day
  // and the beginning of the first on the next
  if ($remainder > $max_periods)
  {
    $remainder += $max_periods - MINUTES_PER_DAY;
  }
  
  // We'll express the duration as an integer, in days if possible, otherwise periods
  if (($remainder == 0) || (($start_period == 0) && ($remainder == $max_periods)))
  {
    $dur = (int) $days;
    if ($remainder == $max_periods)
    {
      $dur++;
    }
    $units = $translate ? get_vocab("days") : "days";
  }
  else
  {
    $dur = (intval($days) * $max_periods) + $remainder;
    $units = $translate ? get_vocab("periods") : "periods";
  }
}

// Converts a period of $units starting at $start_period into seconds, when it is
// originally expressed in $dur_units (periods or days).   (Almost the inverse of
// toPeriodString(), but note that toPeriodString() can do language translation)
function fromPeriodString($start_period, &$units, $dur_units)
{
  global $periods;
  
  if (!isset($units) || !isset($dur_units))
  {
    return;
  }
  
  // First get the duration in minutes
  $max_periods = count($periods);
  if ($dur_units == "periods")
  {
    $end_period = $start_period + $units;
    if ($end_period > $max_periods)
    {
      $units = (MINUTES_PER_DAY * floor($end_period/$max_periods)) + ($end_period%$max_periods) - $start_period;
    }
  }
  if ($dur_units == "days")
  {
    if ($start_period == 0)
    {
      $units = $max_periods + ($units-1)*MINUTES_PER_DAY;
    }
    else
    {
      $units = $units * MINUTES_PER_DAY;
    }
  }
  
  // Then convert into seconds
  $units = (int) $units;
  $units = 60 * $units;
}


// Splits a BYDAY string into its ordinal and day parts, returned as a simple array.
// For example "-1SU" is returned as array("-1", "SU");
function byday_split($byday)
{
  $result = array();
  $split_pos = strlen($byday) -2;
  $result[] = substr($byday, 0, $split_pos);
  $result[] = substr($byday, $split_pos, 2);
  return $result;
}


// Returns the BYDAY value for a given timestamp, eg 4SU for fourth Sunday in
// the month, or -1MO for the last Monday.
function date_byday($timestamp)
{
  global $RFC_5545_days;

  $dow = $RFC_5545_days[date('w', $timestamp)];
  $dom = date('j', $timestamp);
  $ord = intval(($dom - 1)/7) + 1;
  if ($ord == 5)
  {
    $ord = -1;
  }
  return $ord . $dow;
}


// Convert an RFC 5545 day to an ordinal number representing the day of the week,
// eg "MO" returns "1"
function RFC_5545_day_to_ord($day)
{
  global $RFC_5545_days;
  
  $tmp = array_keys($RFC_5545_days, $day);
  return $tmp[0];
}


// Converts a BYDAY (eg "2SU") value for a given year and month into a
// day of the month.   Returns FALSE if the day does not exist (eg for "5SU"
// when there are only four Sundays in the month)
function byday_to_day($year, $month, $byday)
{
  // First of all normalise the month and year, as we allow $month > 12
  while ($month > 12)
  {
    $month -= 12;
    $year++;
  }
  // Get the ordinal number and the day of the week
  list($ord, $dow) = byday_split($byday);
  // Get the starting day of the month
  $start_dom = ($ord > 0) ? 1 : date('t', mktime(0, 0, 0, $month, 1, $year));
  // Get the starting day of the week
  $start_dow = date('w', mktime(0, 0, 0, $month, $start_dom, $year));
  // Turn the BYDAY day of the week into an integer
  $byday_dow = RFC_5545_day_to_ord($dow);
  // get the difference in days
  $diff = $byday_dow - $start_dow;
  $diff += ($ord > 0) ? 7 : -7;
  $diff = $diff %7;
  // add in the weeks
  $diff += ($ord > 0) ? ($ord - 1) * 7 : ($ord + 1) * 7;
  
  $day = $start_dom + $diff;
  
  if (checkdate($month, $day, $year))
  {
    return $day;
  }
  else
  {
    return FALSE;
  }
}


// Returns TRUE if the time $hm1 is before $hm2
// $hm1 and $hm2 are associative arrays indexed by 'hours' and 'minutes'.
// The indices are chosen to allow the result of the PHP getdate() function
// to be passed as parameters
function hm_before($hm1, $hm2)
{
  return ($hm1['hours'] < $hm2['hours']) ||
         (($hm1['hours'] == $hm2['hours']) && ($hm1['minutes'] < $hm2['minutes']));
}


// Returns TRUE if the end of the last slot is on the day after the beginning
// of the first slot
function day_past_midnight()
{
  global $morningstarts, $morningstarts_minutes, $eveningends, $eveningends_minutes, $resolution;
  
  $start_first_slot = (($morningstarts * 60) + $morningstarts_minutes) * 60;
  $end_last_slot = ((($eveningends * 60) + $eveningends_minutes) * 60) + $resolution;
  $end_last_slot = $end_last_slot % SECONDS_PER_DAY;
  
  return ($end_last_slot <= $start_first_slot);
}


// Gets the UNIX timestamp for the start of the first slot on the given day
function get_start_first_slot($month, $day, $year)
{
  global $morningstarts, $morningstarts_minutes;
  
  $t = mrbs_mktime($morningstarts, $morningstarts_minutes, 0,
                   $month, $day, $year);
  return $t;
}


// Gets the UNIX timestamp for the start of the last slot on the given day
function get_start_last_slot($month, $day, $year)
{
  global $morningstarts, $morningstarts_minutes, $eveningends, $eveningends_minutes;
  
  // Work out if $evening_ends is really on the next day
  if (hm_before(array('hours' => $eveningends, 'minutes' => $eveningends_minutes),
                array('hours' => $morningstarts, 'minutes' => $morningstarts_minutes)))
  {
    $day++;
  }
  $t = mrbs_mktime($eveningends, $eveningends_minutes, 0,
                   $month, $day, $year);
  return $t;
}


// Get the duration of an interval given a start time and end time.  Corrects for
// DST changes so that the duration is what the user would expect to see.  For
// example 12 noon to 12 noon crossing a DST boundary is 24 hours.
//
// Returns an array indexed by 'duration' and 'dur_units'
//
//    $start_time     int     start time as a Unix timestamp
//    $end_time       int     end time as a Unix timestamp
//    $enable_periods boolean whether we are using periods
//    $translate      boolean whether to translate into the browser language
function get_duration($start_time, $end_time, $enable_periods, $translate=TRUE)
{
  $result = array();
  $result['duration'] = $end_time - $start_time;
  // Need to make DST correct in opposite direction to entry creation
  // so that user see what he expects to see
  $result['duration'] -= cross_dst($start_time, $end_time);
  if ($enable_periods)
  {
    $time = getdate($start_time);
    $start_period = $time['minutes'];
    toPeriodString($start_period, $result['duration'], $result['dur_units'], $translate);
  }
  else
  {
    toTimeString($result['duration'], $result['dur_units'], $translate);
  }
  return $result;
}


// Generate a checkbox with an associated label
//
//   $params    an associative array holding the function parameters:
//      MANDATORY
//        'label'       The text to be used for the field label.
//        'name'        The name of the element.
//      OPTIONAL
//        'label_after' Whether to put the label before or after the checkbox.  Default FALSE
//        'id'          The id of the element.  Defaults to be the same as the name.
//        'value'       The value of the input.  Default ''
//        'class'       A class (or array of classes) to give the element.  Default NULL
//        'disabled'    Whether the field should be disabled.  Default FALSE
//        'mandatory'   Whether the field is a required field.  Default FALSE
//        'attributes'  Additional attributes not covered explicitly above.  Default NULL.
//                      Can be either a simple string or an array of attributes.
//
function generate_checkbox($params)
{
  // some sanity checking on params
  foreach (array('label', 'name', 'id', 'label_after', 'value', 'class',
                 'disabled', 'mandatory', 'attributes') as $key)
  {
    if (!isset($params[$key]))
    {
      switch ($key)
      {
        case 'label':
        case 'name':
          trigger_error('Missing mandatory parameters', E_USER_NOTICE);
          break;
        case 'id':
          $params[$key] = $params['name'];
          break;
        case 'value':
          $params[$key] = '';
          break;
        case 'disabled':
        case 'label_after':
        case 'mandatory':
          $params[$key] = FALSE;
          break;
        case 'class':
          $params[$key] = array();
          break;
        default:
          break;
      }
    }
  }
  
  if (isset($params['attributes']) && is_array($params['attributes']))
  {
    $params['attributes'] = implode(' ', $params['attributes']);
  }
  
  if (!is_array($params['class']))
  {
    $params['class'] = array($params['class']);
  }
  $params['class'][] = 'checkbox';
  
  // generate the HTML
  // no HTML escaping for the label - it is trusted
  $html  = "<label for=\"" . $params['id'] . "\"";
  $html .= ($params['label_after']) ? ' class="secondary"' : '';
  $html .= ">";
  if (!$params['label_after'])
  {
    $html .= $params['label'] . "</label>";
  }
  $html .= "<input type=\"checkbox\"";
  $html .= (count($params['class']) > 0) ? ' class="' . implode(' ', $params['class']) . '"' : '';
  $html .= " id=\"" . $params['id'] . "\" name=\"" . $params['name'] . "\" value=\"1\"";
  $html .= (empty($params['value'])) ? "" : " checked=\"checked\"";
  $html .= ($params['disabled']) ? " disabled=\"disabled\"" : "";
  $html .= ($params['mandatory']) ? " required aria-required=\"true\"" : "";
  $html .= (isset($params['attributes'])) ? " " . $params['attributes'] : '';
  $html .= ">";
  if ($params['label_after'])
  {
    $html .= $params['label'] . "</label>";
  }
  $html .= "\n";
  
  // and a hidden input if the input box is disabled and the value is true
  // (the checkbox isn't posted if not checked)
  if (($params['disabled']) && !empty($params['value']))
  {
    $html .= "<input type=\"hidden\" value=\"1\" name=\"" . $params['name'] . "\">\n";
  }
  
  echo $html;
}


// Generate an ordinary input field, ie an <input>, with an associated label
//
//   $params    an associative array holding the function parameters:
//      MANDATORY
//        'name'          The name of the input.
//      OPTIONAL
//        'label'         The text to be used for the field label.
//        'label_title'   The text to be used for the title attribute for the field label
//        'value'         The value of the input.  Default ''
//        'type'          The type of input, eg 'text', 'number', etc.  Default NULL
//        'step'          The value of the 'step' attribute.  Default NULL
//        'min'           The value of the 'min' attribute.  Default NULL
//        'max'           The value of the 'max' attribute.  Default NULL
//        'suffix'        A string that is displayed after the input field
//        'disabled'      Whether the field should be disabled.  Default FALSE
//        'create_hidden' Boolean.  If TRUE hidden inputs are created if 'disabled' is set
//                        Default TRUE
//        'mandatory'     Whether the field is a required field.  Default FALSE
//        'maxlength'     The maximum length of input allowed.   Default NULL (no limit)
//        'attributes'    Additional attributes not covered explicitly above.  Default NULL.
//                        Can be either a simple string or an array of attributes.
//
function generate_simple_input($params)
{
  // some sanity checking on params
  foreach (array('label', 'label_title', 'name', 'value', 'type', 'step', 'disabled',
                 'create_hidden', 'mandatory', 'maxlength', 'attributes', 'suffix') as $key)
  {
    if (!isset($params[$key]))
    {
      switch ($key)
      {
        case 'name':
          trigger_error('Missing mandatory parameters', E_USER_NOTICE);
          break;
        case 'value':
        case 'label_title':
          $params[$key] = '';
          break;
        case 'disabled':
        case 'mandatory':
          $params[$key] = FALSE;
          break;
        case 'create_hidden':
          $params[$key] = TRUE;
          break;
        default:
          break;
      }
    }
  }
  
  if (isset($params['attributes']) && is_array($params['attributes']))
  {
    $params['attributes'] = implode(' ', $params['attributes']);
  }
  
  // generate the HTML
  $html = '';
  if (isset($params['label']))
  {
    // no HTML escaping for the label - it is trusted
    $html .= "<label for=\"" . $params['name'] . "\"";
    $html .= (empty($params['label_title'])) ? '' : ' title="' . htmlspecialchars($params['label_title']) . '"';
    $html .= ">" . $params['label'] . "</label>\n";
  }
  $html .= "<input";
  $html .= (isset($params['type'])) ? " type=\"" . $params['type'] . "\"" : "";
  $html .= (isset($params['step'])) ? " step=\"" . $params['step'] . "\"" : "";
  $html .= (isset($params['min'])) ? " min=\"" . $params['min'] . "\"" : "";
  $html .= (isset($params['max'])) ? " max=\"" . $params['max'] . "\"" : "";
  $html .= (isset($params['pattern'])) ? " pattern=\"" . htmlspecialchars($params['pattern']) . "\"" : "";
  $html .= (isset($params['attributes'])) ? " " . $params['attributes'] : "";
  $html .= " id=\"" . $params['name'] . "\" name=\"" . $params['name'] . "\"";
  $html .= ($params['disabled']) ? " disabled=\"disabled\"" : '';
  $html .= ($params['mandatory']) ? " required aria-required=\"true\"" : '';
  $html .= (isset($params['maxlength'])) ? " maxlength=\"" . $params['maxlength'] . "\"" : '';
  // Don't give an empty string if it's a number as that's not a valid floating point number
  // and will fail HTML5 validation
  if (($params['value'] !== '') ||
      (isset($params['type']) && ($params['type'] != 'number')) )
  {
    $html .= " value=\"" . htmlspecialchars($params['value']) . "\"";
  }
  $html .= ">";
  if (isset($params['suffix']))
  {
    $html .= "<span>" . $params['suffix'] . "</span>";
  }
  $html .= "\n";
  // and a hidden input if the input box is disabled
  if ($params['disabled'] && $params['create_hidden'])
  {
    $html .= "<input type=\"hidden\" name=\"" . $params['name'] . "\" value=\"".
      htmlspecialchars($params['value'])."\">\n";
  }
  echo $html;
}


// Generate an input which will be a <select> element if $select_options is set
// for the field, otherwise a <datalist> element if $datalist_options is set,
// otherwise an ordinary <input> field.
//
//   $params    an associative array holding the function parameters:
//      MANDATORY
//        'name'          The name of the input.
//      OPTIONAL
//        'field'         The name of the field, eg 'entry.name' as used by
//                        $select_options and $datalist_options
//        'label'         The text to be used for the field label.
//        'label_title'   The text to be used for the title attribute for the field label
//        'value'         The value of the input.  Default ''
//        'suffix'        A string that is displayed after the input field
//        'disabled'      Whether the field should be disabled.  Default FALSE
//        'create_hidden' Boolean.  If TRUE hidden inputs are created if 'disabled' is set
//                        Default TRUE
//        'mandatory'     Whether the field is a required field.  Default FALSE
//        'maxlength'     The maximum length of input allowed.   Default NULL (no limit)
//        'attributes'    Additional attributes, allowing HTML5 input types such as number and
//                        email to be used.   Note that additional attributes such as min, etc.
//                        can also be included in the string, eg 'type="number" min="1" step="1"'.
//                        Default NULL.  Can be either a simple string or an array of attributes
function generate_input($params)
{
  global $select_options, $datalist_options;
  
  if (isset($params['field']) && isset($select_options[$params['field']]))
  {
    $params['options']   = $select_options[$params['field']];
    generate_select($params);
  }
  elseif (isset($params['field']) && isset($datalist_options[$params['field']]))
  {
    $params['options']   = $datalist_options[$params['field']];
    generate_datalist($params);
  }
  else
  {
    generate_simple_input($params);
  }
}


// Generate a single radio button (useful when you want to arrange the members
// of a radio group slightly differently from the standard way)
//
//   $params    an associative array holding the function parameters:
//      MANDATORY
//        'name'          The name of the input.
//      OPTIONAL
//        'value'         The value of the input.  Default ''
//        'options'       An associative array where the key is the value of the
//                        button and the value is the button text
//        'disabled'      Whether the field should be disabled.  Default FALSE
//        'create_hidden' Boolean.  If TRUE hidden inputs are created if 'disabled' is set
//                        Default TRUE
//
function generate_radio($params)
{
  // some sanity checking on params
  foreach (array('label', 'name', 'options', 'value', 'disabled', 'create_hidden') as $key)
  {
    if (!isset($params[$key]))
    {
      switch ($key)
      {
        case 'name':
          trigger_error('Missing mandatory parameters', E_USER_NOTICE);
          break;
        case 'options':
          $params[$key] = array();
          break;
        case 'value':
          $params[$key] = '';
          break;
        case 'disabled':
          $params[$key] = FALSE;
          break;
        case 'create_hidden':
          $params[$key] = TRUE;
          break;
        default:
          break;
      }
    }
  }

  // get the value and the text for the button.   Although there should only
  // be one element in the options array we use an array for consistency
  // with the other functions
  $option = each($params['options']);
  // generate the HTML
  // no HTML escaping for the label - it is trusted
  $html  = "<label class=\"radio\">";
  $html .= "<input class=\"radio\" type=\"radio\" name=\"" . $params['name'] . "\" value=\"" . htmlspecialchars($option['key']) . "\"";          
  $html .= ($params['value'] == $option['key']) ? " checked=\"checked\"" : "";
  $html .= ($params['disabled']) ? " disabled=\"disabled\"" : "";
  $html .= ">" . htmlspecialchars($option['value']);
  $html .= "</label>\n";
  
  // and a hidden input if the input box is disabled and the value is true
  // (the checkbox isn't posted if not checked)
  if ($params['disabled'] && $params['create_hidden'] && ($params['value'] == $option['key']))
  {
    $html .= "<input type=\"hidden\" value=\"" . htmlspecialchars($option['key']) . "\" name=\"" . $params['name'] . "\">\n";
  }
  echo $html;
}


// Generate a group of radio buttons with an associated label
//
//   $params    an associative array holding the function parameters:
//      MANDATORY
//        'label'         The text to be used for the field label.
//        'name'          The name of the input.
//        'options'       An array of options for the radio buttons.   Can be a simple
//                        array or an associative array with value => label members for
//                        each button.
//      OPTIONAL
//        'label_title'   The text to be used for the title attribute for the field label
//        'value'         The value of the input.  Defaults to the first element of 'options'
//                        (mirroring the behaviour of a <select> element)
//        'force_assoc'   Boolean.  Forces the options array to be treated as an
//                        associative array.  Default FALSE, ie it is treated as whatever
//                        it looks like.  (This parameter is necessary because if you
//                        index an array with strings that look like integers then PHP
//                        casts the keys to integers and the array becomes a simple array)
//        'disabled'      Whether the field should be disabled.  Default FALSE
//        'create_hidden' Boolean.  If TRUE hidden inputs are created if 'disabled' is set
//                        Default TRUE
//
function generate_radio_group($params)
{
  // some sanity checking on params
  // 'options' and 'force_assoc' must come before 'value' in the array
  foreach (array('label', 'label_title', 'name', 'options', 'force_assoc', 'value',
                 'disabled', 'create_hidden') as $key)
  {
    if (!isset($params[$key]))
    {
      switch ($key)
      {
        case 'label':
        case 'name':
        case 'options':
          trigger_error('Missing mandatory parameters', E_USER_NOTICE);
          break;
        case 'options':
          $params[$key] = array();
          break;
        case 'label_title':
          $params[$key] = '';
          break;
        case 'value':
          if (is_assoc($params['options']) || $params['force_assoc'])
          {
            $array_keys = array_keys($params['options']);
            $params[$key] = $array_keys[0];
          }
          else
          {
            $params[$key] = $params['options'][0];
          }
          break;
        case 'disabled':
        case 'force_assoc':
          $params[$key] = FALSE;
          break;
        case 'create_hidden':
          $params[$key] = TRUE;
          break;
        default:
          break;
      }
    }
  }
  // generate the HTML
  // no HTML escaping for the label - it is trusted
  $html  = "<label";
  $html .= (empty($params['label_title'])) ? '' : ' title="' . htmlspecialchars($params['label_title']) . '"';
  $html .= ">" . $params['label'] . "</label>\n";
  $html .= "<div class=\"group\">\n";
  echo $html;
  
  // Output each radio button
  while ($option = each($params['options']))
  {
    // We can cope with both associative and ordinary arrays
    $button_label = $option['value'];
    if (is_assoc($params['options']) || $params['force_assoc'])
    {
      $button_value = $option['key'];
    }
    else
    {
      $button_value = $button_label;
    }
    generate_radio(array('name'          => $params['name'],
                         'options'       => array($button_value => $button_label),
                         'value'         => $params['value'],
                         'disabled'      => $params['disabled'],
                         'create_hidden' => $params['create_hidden']));
  }

  $html = "</div>\n";
  if ($params['disabled'] && $params['create_hidden'])
  {
    $html .= "<input type=\"hidden\" name=\"" . $params['name'] . "\"";
    $html .= " value=\"" . htmlspecialchars($params['value']) . "\">\n";
  }
  echo $html;
}


// Generate a group of checkboxes with an associated label
//
//   $params    an associative array holding the function parameters:
//      MANDATORY
//        'label'       The text to be used for the field label.
//        'name'        The name of the input.
//      OPTIONAL
//        'value'       The value of the input.  Can be an array. Default array()
//        'options'     An array of options for the checkboxes.   Can be a simple
//                      array or an associative array with value => label members for
//                      each checkbox.   Default is an empty array.
//        'force_assoc' Boolean.  Forces the options array to be treated as an
//                      associative array.  Default FALSE, ie it is treated as whatever
//                      it looks like.  (This parameter is necessary because if you
//                      index an array with strings that look like integers then PHP
//                      casts the keys to integers and the array becomes a simple array)
//        'disabled'    Whether the field should be disabled.  Default FALSE
//
function generate_checkbox_group($params)
{
  // some sanity checking on params
  foreach (array('label', 'name', 'options', 'force_assoc', 'value', 'disabled') as $key)
  {
    if (!isset($params[$key]))
    {
      switch ($key)
      {
        case 'label':
        case 'name':
          trigger_error('Missing mandatory parameters', E_USER_NOTICE);
          break;
        case 'options':
          $params[$key] = array();
          break;
        case 'value':
          $params[$key] = array();
          break;
        case 'force_assoc':
        case 'disabled':
          $params[$key] = FALSE;
          break;
        default:
          break;
      }
    }
  }
  
  if (!is_array($params['value']))
  {
    $params['value'] = array($params['value']);
  }
  
  // generate the HTML
  // no HTML escaping for the label - it is trusted
  $html  = "<label>" . $params['label'] . "</label>\n";
  $html .= "<div class=\"group\">\n";
  // Output each checkbox
  foreach ($params['options'] as $value => $token)
  {
    // We can cope with both associative and ordinary arrays
    if (!$params['force_assoc'] && !is_assoc($params['options']))
    {
      $value = $token;
    }
    $html .= "<label>";
    $html .= "<input class=\"checkbox\" type=\"checkbox\" name=\"" . $params['name'] . "\"";
    $html .= " value=\"" . htmlspecialchars($value) . "\"";          
    $html .= (in_array($value, $params['value'])) ? " checked=\"checked\"" : "";
    $html .= ($params['disabled']) ? " disabled=\"disabled\"" : "";
    $html .= ">" . htmlspecialchars($token);
    $html .= "</label>\n";
    if ($params['disabled'] && in_array($value, $params['value']))
    {
      $html .= "<input type=\"hidden\" name=\"" . $params['name'] . "\"";
      $html .= " value=\"" . htmlspecialchars($value) . "\">\n";
    }
  }
  $html .= "</div>\n";
  
  echo $html;
}


// Generates a select box with an associated label
//
//   $params    an associative array holding the function parameters:
//      MANDATORY
//        'name'          The name of the element.
//      OPTIONAL
//        'id'            The id of the element.  Defaults to the same as the name.
//        'label'         The text to be used for the field label.
//        'label_title'   The text to be used for the title attribute for the field label
//        'options'       An array of options for the select element.   Can be a simple
//                        array or an associative array with value => text members for
//                        each <option> in the <select> element.   Default is an empty array.
//        'force_assoc'   Boolean.  Forces the options array to be treated as an
//                        associative array.  Default FALSE, ie it is treated as whatever
//                        it looks like.  (This parameter is necessary because if you
//                        index an array with strings that look like integers then PHP
//                        casts the keys to integers and the array becomes a simple array)
//        'value'         The value of the input.  Default ''.   Can be a single value
//                        or an array of values.
//        'size'          The value of the 'size' attribute.  Default NULL
//        'disabled'      Whether the field should be disabled.  Default FALSE
//        'create_hidden' Boolean.  If TRUE hidden inputs are created if 'disabled' is set
//                        Default TRUE
//        'mandatory'     Whether the field is a required field.  Default FALSE
//        'multiple'      Whether multiple selections are allowed.  Default FALSE
//        'attributes'    Additional attributes not covered explicitly above.  Default NULL.
//                        Can be either a simple string or an array of attributes.
//
function generate_select($params)
{
  // some sanity checking on params
  foreach (array('label', 'label_title', 'name', 'id', 'options', 'force_assoc',
                 'value', 'size', 'disabled', 'create_hidden', 'mandatory',
                 'multiple', 'attributes') as $key)
  {
    if (!isset($params[$key]))
    {
      switch ($key)
      {
        case 'name':
          trigger_error('Missing mandatory parameters', E_USER_NOTICE);
          break;
        case 'id':
          $params[$key] = $params['name'];
          break;
        case 'label_title':
          $params[$key] = '';
          break;
        case 'options':
          $params[$key] = array();
          break;
        case 'value':
          $params[$key] = array();
          break;
        case 'force_assoc':
        case 'disabled':
        case 'mandatory':
        case 'multiple':
          $params[$key] = FALSE;
          break;
        case 'create_hidden':
          $params[$key] = TRUE;
          break;
        default:
          break;
      }
    }
  }
  
  // Some checking that we're obeying the HTML5 rules.   A bit pedantic, but
  // helps with debugging.  Only carry out these checks if we're going to be
  // able to report them.
  if (error_reporting() & E_USER_NOTICE)
  {
    if ((!isset($params['size']) || ($params['size'] == 1)) &&
        $params['mandatory'] &&
        !$params['multiple'])
    {
      if (count($params['options']) == 0)
      {
        $message =  "A select element with a required attribute and without a multiple " .
                    "attribute, and whose size is 1, must have a child option element.";
        trigger_error($message, E_USER_NOTICE);
      }
      else
      {
        $first_child = each($params['options']);
        if (($first_child['key'] !== '') && ($first_child['value'] !== ''))
        {
          $message = "The first child option element of a select element with a required " .
                     "attribute and without a multiple attribute, and whose size is 1, " .
                     "must have either an empty value attribute, or must have no text content.";
          trigger_error($message, E_USER_NOTICE);
        }
      }
    }
  }
  
  if (!is_array($params['value']))
  {
    $params['value'] = array($params['value']);
  }
  
  if (isset($params['attributes']) && is_array($params['attributes']))
  {
    $params['attributes'] = implode(' ', $params['attributes']);
  }
  
  // generate the HTML
  // no HTML escaping for the label - it is trusted
  $html = '';
  if (isset($params['label']))
  {
    $html .= "<label for=\"" .$params['id'] . "\"";
    $html .= (empty($params['label_title'])) ? '' : ' title="' . htmlspecialchars($params['label_title']) . '"';
    $html .= ">" . $params['label'] . "</label>\n";
  }
  $html .= "<select id=\"" . $params['id'] . "\" name=\"" . $params['name'] . "\"";
  $html .= ($params['disabled']) ? " disabled=\"disabled\"" : "";
  $html .= ($params['mandatory']) ? " required aria-required=\"true\"" : "";
  $html .= ($params['multiple']) ? " multiple" : "";
  $html .= (isset($params['attributes'])) ? " " . $params['attributes'] : "";
  $html .= ">\n";

  foreach ($params['options'] as $value => $text)
  {
    // We can cope with both associative and ordinary arrays
    if (!$params['force_assoc'] && !is_assoc($params['options']))
    {
      $value = $text;
    }
    $html .= "<option value=\"" . htmlspecialchars($value) . "\"";
    $html .= (in_array($value, $params['value'])) ? " selected=\"selected\"" : '';
    $html .= ">".htmlspecialchars($text)."</option>\n";
  }

  $html .= "</select>\n";
  
  // and hidden inputs if the select box is disabled
  if ($params['disabled'] && $params['create_hidden'])
  {
    foreach ($params['value'] as $value)
    {
      $html .= "<input type=\"hidden\" name=\"" . $params['name'] . "\" value=\"".
               htmlspecialchars($value)."\">\n";
    }
  }
  
  echo $html;
}


// Generates a datalist element with an associated label
//
//   $params    an associative array holding the function parameters:
//      MANDATORY
//        'name'          The name of the element.
//      OPTIONAL
//        'id'            The id of the element.  Defaults to the same as the name.
//        'label'         The text to be used for the field label.
//        'label_title'   The text to be used for the title attribute for the field label
//        'options'       An array of options for the select element.   Can be a simple
//                        array or an associative array with value => text members for
//                        each <option> in the <select> element.   Default is an empty array.
//        'force_assoc'   Boolean.  Forces the options array to be treated as an
//                        associative array.  Default FALSE, ie it is treated as whatever
//                        it looks like.  (This parameter is necessary because if you
//                        index an array with strings that look like integers then PHP
//                        casts the keys to integers and the array becomes a simple indexed array)
//        'force_indexed' Boolean.  Forces the options array to be treated as a simple
//                        indexed array, ie just the values are used and the keys are
//                        ignored.   Default FALSE, ie it is treated as whatever it looks
//                        like.
//        'value'         The value of the input.  Default ''.
//        'disabled'      Whether the field should be disabled.  Default FALSE
//        'create_hidden' Boolean.  If TRUE hidden inputs are created if 'disabled' is set
//                        Default TRUE
//        'mandatory'     Whether the field is a required field.  Default FALSE
//        'attributes'    Additional attributes not covered explicitly above.  Default NULL.
//                        Can be either a simple string or an array of attributes.
//
function generate_datalist($params)
{
  global $HTTP_USER_AGENT;
  
  // some sanity checking on params
  foreach (array('label', 'label_title', 'name', 'id', 'options', 'force_assoc',
                 'force_indexed', 'value', 'disabled', 'create_hidden', 'mandatory',
                 'attributes') as $key)
  {
    if (!isset($params[$key]))
    {
      switch ($key)
      {
        case 'name':
          trigger_error('Missing mandatory parameters', E_USER_NOTICE);
          break;
        case 'id':
          $params[$key] = $params['name'];
          break;
        case 'options':
          $params[$key] = array();
          break;
        case 'value':
        case 'label_title':
          $params[$key] = '';
          break;
        case 'force_assoc':
        case 'force_indexed':
        case 'disabled':
        case 'mandatory':
          $params[$key] = FALSE;
          break;
        case 'create_hidden':
          $params[$key] = TRUE;
          break;
        default:
          break;
      }
    }
  }
  
  if (isset($params['attributes']) && is_array($params['attributes']))
  {
    $params['attributes'] = implode(' ', $params['attributes']);
  }
  
  // generate the HTML
  // no HTML escaping for the label - it is trusted
  $html = '';
  if (isset($params['label']))
  {
    $html .= "<label for=\"" .$params['id'] . "\"";
    $html .= (empty($params['label_title'])) ? '' : ' title="' . htmlspecialchars($params['label_title']) . '"';
    $html .= ">" . $params['label'] . "</label>\n";
  }
  $html .= "<input id=\"" . $params['id'] . "\" name=\"" . $params['name'] . "\"";
  $html .= " list=\"" . $params['id'] . "_options\"";
  $html .= " value=\"" . $params['value'] . "\"";
  $html .= ($params['disabled']) ? " disabled=\"disabled\"" : "";
  $html .= ($params['mandatory']) ? " required aria-required=\"true\"" : "";
  $html .= (isset($params['attributes'])) ? " " . $params['attributes'] : "";
  // We normally have the autocomplete atribute set to off because in most browsers
  // this stops the browser suggesting previous input and confines the list to our
  // options.   However in Opera turning off autocomplete turns off our options as
  // well, so we have to turn it on.  (We'll also turn it on for Opera in the JavaScript,
  // for good measure, just in case the server isn't passing through the user agent).
  $html .= " autocomplete=\"";
  $html .= (strpos(strtolower($HTTP_USER_AGENT), 'opera') === FALSE) ? 'off' : 'on';
  $html .= "\">\n";
  $html .= "<datalist id=\"" . $params['id'] . "_options\">";
  
  // Put a <select> wrapper around the options so that browsers that don't
  // support <datalist> will still have the options in their DOM and then
  // the JavaScript polyfill can find them and do something with them
  $html .= "<select style=\"display: none\">\n";

  foreach ($params['options'] as $value => $text)
  {
    // We can cope with both associative and ordinary arrays
    if (!$params['force_assoc'] && !is_assoc($params['options']))
    {
      $value = $text;
    }
    $html .= "<option";
    if (!$params['force_indexed'])
    {
      $html .= " value=\"" . htmlspecialchars($value) . "\"";
    }
    $html .= ">" . htmlspecialchars($text) . "</option>\n";
  }
  $html .= "</select>\n";
  $html .= "</datalist>\n";
  
  // and hidden inputs if the input is disabled
  if ($params['disabled'] && $params['create_hidden'])
  {
      $html .= "<input type=\"hidden\" name=\"" . $params['name'] . "\" value=\"".
               htmlspecialchars($params['value']) . "\">\n";
  }
  
  echo $html;
}


// Generate a textarea with an associated label
//
//   $params    an associative array holding the function parameters:
//      MANDATORY
//        'label'         The text to be used for the field label.
//        'name'          The name of the input.
//      OPTIONAL
//        'value'         The value of the input.  Default ''
//        'label_title'   The text to be used for the title attribute for the field label
//        'disabled'      Whether the field should be disabled.  Default FALSE
//        'create_hidden' Boolean.  If TRUE hidden inputs are created if 'disabled' is set
//                        Default TRUE
//        'mandatory'     Whether the field is a required field.  Default FALSE
//        'maxlength'     The maximum length of input allowed.   Default NULL (no limit)
//        'attributes'    Additional attributes not covered explicitly above.  Default NULL.
//                        Can be either a simple string or an array of attributes.
//
function generate_textarea($params)
{
  // some sanity checking on params
  foreach (array('label', 'label_title', 'name', 'value', 'disabled', 'create_hidden',
                 'mandatory', 'maxlength', 'attributes') as $key)
  {
    if (!isset($params[$key]))
    {
      switch ($key)
      {
        case 'label':
        case 'name':
          trigger_error('Missing mandatory parameters', E_USER_NOTICE);
          break;
        case 'value':
        case 'label_title':
          $params[$key] = '';
          break;
        case 'disabled':
        case 'mandatory':
          $params[$key] = FALSE;
          break;
        case 'create_hidden':
          $params[$key] = TRUE;
          break;
        default:
          break;
      }
    }
  }
  
  if (isset($params['attributes']) && is_array($params['attributes']))
  {
    $params['attributes'] = implode(' ', $params['attributes']);
  }
  
  // generate the HTML
  // no HTML escaping for the label - it is trusted
  $html  = "<label for=\"" . $params['name'] . "\"";
  $html .= (empty($params['label_title'])) ? '' : ' title="' . htmlspecialchars($params['label_title']) . '"';
  $html .= ">" . $params['label'] . "</label>\n";
  // textarea rows and cols are overridden by CSS height and width
  $html .= "<textarea id=\"" . $params['name'] . "\" name=\"" . $params['name'] . "\"";
  $html .= (isset($params['attributes'])) ? " " . $params['attributes'] : "";
  $html .= ($params['disabled']) ? " disabled=\"disabled\"" : '';
  $html .= ($params['mandatory']) ? " required aria-required=\"true\"" : '';
  $html .= (isset($params['maxlength'])) ? " maxlength=\"" . $params['maxlength'] . "\"" : '';
  $html .= ">" . htmlspecialchars($params['value']) . "</textarea>\n";
  // and a hidden input if the textarea is disabled
  if ($params['disabled'] && $params['create_hidden'])
  {
    $html .= "<input type=\"hidden\" name=\"" . $params['name'] . "\" value=\"".
      htmlspecialchars($params['value'])."\">\n";
  }
  echo $html;
}

// Generates a date selector for use on a form.   If JavaScript is enabled
// then it will generate a calendar picker using jQuery UI datepicker.   If not,
// it will generate three separate select boxes, one each for day, month and year.
//
// $form_id is an optional fifth parameter.   If set it specifies the id of
// a form to submit when the datepicker is closed.
//
// $disabled:  TRUE if the SELECT is to be disabled (Note: hidden inputs will be
// created if the SELECT is disabled)
//
// Whether or not JavaScript is enabled the date is passed back in three separate
// variables:  ${prefix}day, ${prefix}month and ${prefix}year
//
// The function passes back three separate variables, rather than a single date 
// variable, partly for compatibility with previous implementations of genDateSelector()
// and partly because it's easier to do this for the non-JavaScript version.
function genDateSelector($prefix, $day, $month, $year, $form_id='', $disabled=FALSE)
{
  global $strftime_format, $year_range;
  
  // Make sure we've got a date
  if (empty($day) or empty($month) or empty($year))
  {
    $day   = date("d");
    $month = date("m");
    $year  = date("Y");
  }

  // Cast the dates to ints to remove any leading zeroes (otherwise
  // JavaScript will treat them as octal numbers)
  $day = (int) $day;
  $month = (int) $month;
  $year = (int) $year;
  
  // First and last dates to show in year select
  $min = min($year, date("Y")) - $year_range['back'];
  $max = max($year, date("Y")) + $year_range['ahead'];
  
  // We'll put the date selector in a span.    First of all we'll generate
  // day, month and year selectors.   These will be used if JavaScript is
  // disabled.    If JavaScript is enabled it will overwrite these with a
  // datepicker calendar.
  echo "<span class=\"dateselector\"" .
            " data-prefix=\"$prefix\"" .
            " data-day=\"$day\"" .
            " data-month=\"$month\"" .
            " data-year=\"$year\"" .
            " data-min-year=\"$min\"" .
            " data-max-year=\"$max\"" .
            " data-form-id=\"$form_id\">\n";
            
  // the day selector
  $options = array();
  for ($i = 1; $i <= 31; $i++)
  {
    $options[] = $i;
  }
  $params = array('name'     => $prefix . 'day',
                  'options'  => $options,
                  'value'    => $day,
                  'disabled' => $disabled);
  generate_select($params);

  // the month selector
  $options = array();
  for ($i = 1; $i <= 12; $i++)
  {
    $options[$i] = utf8_strftime($strftime_format['mon'], mktime(0, 0, 0, $i, 1, $year));
  }
  $params = array('name'        => $prefix . 'month',
                  'options'     => $options,
                  'force_assoc' => TRUE,
                  'value'       => $month,
                  'disabled'    => $disabled);
  generate_select($params);

  // the year selector
  $options = array();
  for ($i = $min; $i <= $max; $i++)
  {
    $options[] = $i;
  }
  $params = array('name'     => $prefix . 'year',
                  'options'  => $options,
                  'value'    => $year,
                  'disabled' => $disabled);
  generate_select($params);

  echo "</span>\n";
}

// Error handler - this is used to display serious errors such as database
// errors without sending incomplete HTML pages. This is only used for
// errors which "should never happen", not those caused by bad inputs.
// If $need_header!=0 output the top of the page too, else assume the
// caller did that. Alway outputs the bottom of the page and exits.
function fatal_error($need_header, $message, $show_form_data = true)
{
  global $simple_trailer, $weekstarts, $view_week_number, $strftime_format;

  if ($need_header)
  {
    print_header(0, 0, 0, 0, "");
  }
  error_log("MRBS: ".$message);

  if ($show_form_data)
  {
    if (!empty($_GET))
    {
      error_log("MRBS GET: ".print_r($_GET, true));
    }
    if (!empty($_POST))
    {
      error_log("MRBS POST: ".print_r($_POST, true));
    }
  }
  if (!empty($_SESSION))
  {
    error_log("MRBS SESSION: ".print_r($_SESSION, true));
  }
  echo "<p>$message</p>";
  output_trailer();
  exit;
}


// Escape a PHP string for use in JavaScript
//
// Based on a function contributed by kongaspar at gmail dot com at 
// http://www.php.net/manual/function.addcslashes.php
function escape_js($str)
{
  return addcslashes($str, "\\\'\"&\n\r<>/");
}


// Remove backslash-escape quoting if PHP is configured to do it with
// magic_quotes_gpc. Use this whenever you need the actual value of a GET/POST
// form parameter (which might have special characters) regardless of PHP's
// magic_quotes_gpc setting.
function unslashes($s)
{
  if (get_magic_quotes_gpc())
  {
    return stripslashes($s);
  }
  else
  {
    return $s;
  }
}

// Return a default area; used if no area is already known. This returns the
// area that contains the default room (if it is set, valid and enabled) otherwise the
// first area in alphabetical order in the database (no guarantee there is an area 1).
// The area must be enabled for it to be considered.
// This could be changed to implement something like per-user defaults.
function get_default_area()
{
  global $tbl_area, $tbl_room, $default_room;
  
  // If the $default_room is set and exists and enabled, then return the
  // corresponding area
  if (isset($default_room))
  {
    $area = sql_query1("SELECT area_id
                          FROM $tbl_room R, $tbl_area A
                         WHERE R.id=$default_room
                           AND R.area_id = A.id
                           AND R.disabled = 0
                           AND A.disabled = 0
                         LIMIT 1");
    if ($area >= 0)
    {
      return $area;
    }
  }
  // Otherwise return the first enabled area in alphabetical order in the database
  $area = sql_query1("SELECT id
                        FROM $tbl_area
                       WHERE disabled=0
                    ORDER BY area_name
                       LIMIT 1");
  return ((!isset($area) || ($area < 0)) ? 0 : $area);
}

// Return a default room given a valid area; used if no room is already known.
// If the area contains $default_room, then it returns $default_room,
// otherwise the first room in sort_key order in the database.
// This could be changed to implement something like per-user defaults.
function get_default_room($area)
{
  global $tbl_room, $default_room;
  // Check to see whether this area contains $default_room
  if (isset($default_room))
  {
    $room = sql_query1("SELECT id
                          FROM $tbl_room
                         WHERE id=$default_room
                           AND area_id=$area
                           AND disabled=0
                         LIMIT 1");
    if ($room >= 0)
    {
      return $room;
    }
  }
  // Otherwise just return the first room (in sortkey order) in the area
  $room = sql_query1("SELECT id
                        FROM $tbl_room
                       WHERE area_id=$area
                         AND disabled=0
                    ORDER BY sort_key
                       LIMIT 1");
  return ($room < 0 ? 0 : $room);
}

// Return an area id for a given room
function get_area($room)
{
  global $tbl_room;
  $area = sql_query1("SELECT area_id FROM $tbl_room WHERE id=$room LIMIT 1");
  return ($area < 0 ? 0 : $area);
}


// Clean up a row from the area table, making sure there are no nulls, casting
// boolean fields into bools and doing some sanity checking
function clean_area_row($row)
{
  global $force_resolution, $area_defaults, $boolean_fields, $private_override_options;
  
  foreach ($row as $key => $value)
  {
    // If the "per area" setting is in the database, then use that.   Otherwise
    // just stick with the default setting from the config file.
    // (don't use the database setting if $force_resolution is TRUE 
    // and we're looking at the resolution field)
    if (($key != 'resolution') || empty($force_resolution))
    {
      $row[$key] = (isset($row[$key])) ? $value : $area_defaults[$key];
    }
    // Cast those fields which are booleans into booleans
    if (in_array($key, $boolean_fields['area']))
    {
      $row[$key] = (bool) $row[$key];
    }
  }
  // Do some sanity checking in case the area table is somehow messed up
  // (1) 'private_override' must be a valid value
  if (array_key_exists('private_override', $row) &&
      !in_array($row['private_override'], $private_override_options))
  {
    $row['private_override'] = 'private';  // the safest default
    $message = "Invalid value for 'private_override' in the area table.  Using '${row['private_override']}'.";
    trigger_error($message, E_USER_WARNING);
  }
  // (2) 'resolution' must be positive
  if (array_key_exists('resolution', $row) &&
      (empty($row['resolution']) || ($row['resolution'] < 0)))
  {
    $row['resolution'] = 30*60;  // 30 minutes, a reasonable fallback
    $message = "Invalid value for 'resolution' in the area table.   Using ${row['resolution']} seconds.";
    trigger_error($message, E_USER_WARNING);
  }
  
  return $row;
}


// Update the default area settings with the ones specific to this area.
// If no value is set in the database, use the value from the config file.
// If $area is empty, use the default area
function get_area_settings($area)
{
  global $tbl_area;
  global $resolution, $default_duration, $default_duration_all_day;
  global $morningstarts, $morningstarts_minutes, $eveningends, $eveningends_minutes;
  global $private_enabled, $private_default, $private_mandatory, $private_override;
  global $min_book_ahead_enabled, $max_book_ahead_enabled, $min_book_ahead_secs, $max_book_ahead_secs;
  global $approval_enabled, $reminders_enabled, $enable_periods;
  global $confirmation_enabled, $confirmed_default, $timezone;
  global $max_per_interval_area_enabled, $max_per_interval_area;
  global $interval_types;
  
  if (empty($area))
  {
    $area = get_default_area();
  }
  
  // Get all the "per area" config settings                  
  $sql = "SELECT timezone, resolution, default_duration, default_duration_all_day,
                 morningstarts, morningstarts_minutes,
                 eveningends, eveningends_minutes,
                 private_enabled, private_default, private_mandatory, private_override,
                 min_book_ahead_enabled, max_book_ahead_enabled,
                 min_book_ahead_secs, max_book_ahead_secs,
                 max_per_day_enabled, max_per_day,
                 max_per_week_enabled, max_per_week,
                 max_per_month_enabled, max_per_month,
                 max_per_year_enabled, max_per_year,
                 max_per_future_enabled, max_per_future,
                 approval_enabled, reminders_enabled, enable_periods,
                 confirmation_enabled, confirmed_default
          FROM $tbl_area 
          WHERE id=$area 
          LIMIT 1";
  $res = sql_query($sql);
  if (!$res || (sql_count($res) == 0))
  {
    // We still need to set the timezone even if the query didn't
    // return any results
    mrbs_default_timezone_set($timezone);
    return;
  }
  else
  {
    $row = clean_area_row(sql_row_keyed($res, 0));
    foreach ($row as $key => $value)
    {
      $$key = $value;
    }
  }
  // Set the timezone
  mrbs_default_timezone_set($timezone);
  
  // Set the $max_per_interval_area_enabled and $max_per_interval_area arrays,
  // which are handled slightly differently
  foreach ($interval_types as $interval_type)
  {
    $var = "max_per_${interval_type}_enabled";
    $max_per_interval_area_enabled[$interval_type] = $$var;
    $var = "max_per_${interval_type}";
    $max_per_interval_area[$interval_type] = $$var;
  }
  
  // If we're using periods then set the resolution to 60 seconds
  if ($enable_periods)
  {
    $resolution = 60;
  }
  
}


// generate the predicate for use in an SQL query to test whether
// an area has $field set
function some_area_predicate($field)
{
  global $area_defaults;
  
  $predicate = "(($field IS NOT NULL) AND ($field > 0))";
  if ($area_defaults[$field])
  {
    $predicate = "(" . $predicate . " OR ($field IS NULL))";
  }
  return $predicate;
}

// Determines whether there is at least one area with the relevant $field
// set (eg 'approval_enabled' or 'confirmation_enabled').   If $enabled
// is TRUE then the search is limited to enabled areas
//
// Returns: boolean
function some_area($field, $enabled=FALSE)
{
  global $tbl_area;
  
  $predicate = some_area_predicate($field);   
  $sql = "SELECT COUNT(*) FROM $tbl_area WHERE $predicate";
  $sql .= ($enabled) ? " AND disabled=0" : "";
  $sql .= " LIMIT 1";                                
  return (sql_query1($sql) > 0);
}

// Get the local day name based on language. Note 2000-01-02 is a Sunday.
function day_name($daynumber, $format=NULL)
{
  global $strftime_format;
  
  if (!isset($format))
  {
    $format = $strftime_format['dayname'];
  }
  
  return utf8_strftime($format, mktime(0,0,0,1,2+$daynumber,2000));
}

// Returns a list of repeat days as a string (eg "Thursday Friday")
//
//    $rep_opt     an array of repeat days or
//                 a string of repeat days that can be used as an array
function get_rep_day_list($rep_opt)
{
  global $weekstarts;
  
  $rep_day_list = "";
  for ($i = 0; $i < 7; $i++)
  {
    $daynum = ($i + $weekstarts) % 7;
    if ($rep_opt[$daynum])
    {
      $rep_day_list .= day_name($daynum) . " ";
    }
  }
  return $rep_day_list;
}


function hour_min_format()
{
  global $twentyfourhour_format, $strftime_format;
  
  if ($twentyfourhour_format)
  {
    return $strftime_format['time24'];
  }
  else
  {
    return $strftime_format['time12'];
  }
}


// Returms a string representing the hour and minute for the nominal
// seconds since the start of the day, $s
function hour_min($s)
{
  $following_day = ($s >= SECONDS_PER_DAY);
  $s = $s % SECONDS_PER_DAY;  // in case $s is on the next day
  // Choose a day that doesn't have any DST transitions in any timezone
  $t = mktime(0, 0, $s, 1, 1, 2000);
  $result = utf8_strftime(hour_min_format(), $t);
  if ($following_day)
  {
    $result = "* " . $result;
  }
  return $result;
}


function period_date_string($t, $mod_time=0)
{
  global $periods, $strftime_format;

  $time = getdate($t);
  $p_num = $time["minutes"] + $mod_time;
  if( $p_num < 0 )
  {
    $p_num = 0;
  }
  if( $p_num >= count($periods) - 1 )
  {
    $p_num = count($periods ) - 1;
  }
  // I have made the separator a ',' as a '-' leads to an ambiguious
  // display in report.php when showing end times.
  return array($p_num, $periods[$p_num] . utf8_strftime(", " . $strftime_format['date'], $t));
}

function period_time_string($t, $mod_time=0)
{
  global $periods;

  $time = getdate($t);
  $p_num = $time["minutes"] + $mod_time;
  if ( $p_num < 0 )
  {
    $p_num = 0;
  }
  if ( $p_num >= count($periods) - 1 )
  {
    $p_num = count($periods ) - 1;
  }
  return $periods[$p_num];
}

function time_date_string($t)
{
  global $twentyfourhour_format, $strftime_format;

  if ($twentyfourhour_format)
  {
    return utf8_strftime($strftime_format['datetime24'], $t);
  }
  else
  {
    return utf8_strftime($strftime_format['datetime12'], $t);
  }
}

// version of the standard PHP function nl2br() that takes account of the fact
// that the optional second parameter is only available from PHP 5.3.0 onwards.
function mrbs_nl2br($string)
{
  if (function_exists('version_compare') && version_compare(PHP_VERSION, '5.3.0', 'ge'))
  {
    return nl2br($string, IS_XHTML);
  }
  else
  {
    return nl2br($string);
  }
}

// Version of the standard PHP function html_entity_decode()
// Although html_entity_decode() was introduced in PHP 4.3.0, support for
// multi-byte character sets was only introduced in PHP 5.0.0.   
// So if we're running PHP5 or higher we'll use the standard
// PHP function; otherwise we'll do the best we can.   At the moment
// we just replace &nbsp; with an ordinary space, which
// should be sufficient in most MRBS circumstances.   This could
// always be extended later to do something more sophisticated if
// necessary.
//
// Note: the charset defaults to UTF-8.  This is slightly different from the
// behaviour in the PHP html_entity_decode() function where the default charset
// was only changed from ISO-8859-1 to UTF-8 in PHP 5.4.0
function mrbs_entity_decode($string, $quote_style=ENT_COMPAT, $charset = 'UTF-8')
{
  if (function_exists('version_compare') && version_compare(PHP_VERSION, '5.0.0', 'ge'))
  {
    $string = html_entity_decode($string, $quote_style, $charset);
  }
  else
  {
    $string = str_replace('&nbsp;', ' ', $string);
  }
  return $string;
}

// validates a comma separated list of email addresses
// returns FALSE if any one of them is invalid, otherwise TRUE
function validate_email_list($list)
{
  require_once 'Mail/RFC822.php';
  (!isset($list)) ? $list = '': '';
  $emails = explode(',', $list);
  $email_validator = new Mail_RFC822();
  foreach ($emails as $email)
  {
    // if no email address is entered, this is OK, even if isValidInetAddress
    // does not return TRUE
    if ( !$email_validator->isValidInetAddress($email, $strict = FALSE)
         && ('' != $list) )
    {
      return FALSE;
    }
  }
  return TRUE;
}


// Display the entry-type color key. This has up to 2 rows, up to 5 columns.
function show_colour_key()
{
  global $booking_types;
  // set the table width.   Default is 5, but try and avoid rows of unequal length
  switch (count($booking_types))
  {
    case '6':
      $table_width = 3;
      break;
    case '8':
    case '12':
      $table_width = 4;
      break;
    default:
      $table_width = 5;
  }
  echo "<table id=\"colour_key\"><tr>\n";
  $n = 0;
  foreach ($booking_types as $key)
  {
    $value = get_type_vocab($key);
    if (++$n > $table_width)
    {
      $n = 1;
      echo "</tr><tr>";
    }
    echo tdcell($key, 1);
    echo "<div class=\"celldiv slots1\" " .  // put the description inside a div which will give clipping in case of long names
    "title=\"$value\">\n";        // but put the name in the title so you can still read it all if you hover over it
    echo "$value</div></td>\n";
  }
  // If there is more than one row and the bottom row isn't complete then 
  // pad it out with a single merged cell
  if ((count($booking_types) > $table_width) && ($n < $table_width))
  {
    echo "<td colspan=\"" . ($table_width - $n) . "\"" .
        " id=\"row_padding\">&nbsp;</td>\n";
  }
  echo "</tr></table>\n";
}

// Round time down to the nearest resolution
function round_t_down($t, $resolution, $am7)
{
  return (int)$t - (int)abs(((int)$t-(int)$am7)
                            % $resolution);
}


// Round time up to the nearest resolution
function round_t_up($t, $resolution, $am7)
{
  if (($t-$am7) % $resolution != 0)
  {
    return $t + $resolution - abs(((int)$t-(int)
                                   $am7) % $resolution);
  }
  else
  {
    return $t;
  }
}


// Returns the nominal (ie ignoring DST transitions) seconds since the start of
// the calendar day on the start of the booking day
function nominal_seconds($t)
{
  global $morningstarts, $morningstarts_minutes;
  
  $date = getdate($t);
  // check to see if the time is really on the next day
  if (hm_before($date,
                array('hours' => $morningstarts, 'minutes' => $morningstarts_minutes)))
  {
    $date['hours'] += 24;
  }
  return (($date['hours'] * 60) + $date['minutes']) * 60;
}


// Returns the index of the period represented by $s nominal seconds
function period_index($s)
{
  // Periods are counted as minutes from noon, ie 1200 is $period[0],
  // 1201 $period[1], etc.
  return intval($s/60) - (12*60);
}


// Returns the name of the period represented by nominal seconds $s
function period_name($s)
{
  global $periods;
  
  return $periods[period_index($s)];
}


// generates some html that can be used to select which area should be
// displayed.
function make_area_select_html($link, $current, $year, $month, $day)
{
  global $tbl_area, $area_list_format;
  
  $out_html = '';
  $sql = "SELECT id, area_name 
            FROM $tbl_area
           WHERE disabled=0
        ORDER BY area_name";
  $res = sql_query($sql);
  // Only show the areas if there's more than one of them, otherwise
  // there's no point
  if ($res  && (sql_count($res) > 1))
  {
    $out_html .= "<div id=\"dwm_areas\">\n";
    $out_html .= "<h3>" . get_vocab("areas") . "</h3>\n";
    if ($area_list_format == "select")
    {
      $out_html .= "<form id=\"areaChangeForm\" method=\"get\" action=\"$link\">\n" .
                   "<div>\n" .
                   "<select class=\"room_area_select\" id=\"area_select\" name=\"area\" onchange=\"this.form.submit()\">";
      for ($i = 0; ($row = sql_row_keyed($res, $i)); $i++)
      {
        $selected = ($row['id'] == $current) ? "selected=\"selected\"" : "";
        $out_html .= "<option $selected value=\"". $row['id']. "\">" . htmlspecialchars($row['area_name']) . "</option>\n";
      }
      // Note:  the submit button will not be displayed if JavaScript is enabled
      $out_html .= "</select>\n" .
                   "<input type=\"hidden\" name=\"day\"   value=\"$day\">\n" .
                   "<input type=\"hidden\" name=\"month\" value=\"$month\">\n" .
                   "<input type=\"hidden\" name=\"year\"  value=\"$year\">\n" .
                   "<input type=\"submit\" class=\"js_none\" value=\"".get_vocab("change")."\">\n" .
                   "</div>\n" .
                   "</form>\n";
    }
    else // list format
    {
      $out_html .= "<ul>\n";
      for ($i = 0; ($row = sql_row_keyed($res, $i)); $i++)
      {
        $out_html .= "<li><a href=\"$link?year=$year&amp;month=$month&amp;day=$day&amp;area=${row['id']}\">";
        $out_html .= "<span" . (($row['id'] == $current) ? ' class="current"' : '') . ">";
        $out_html .= htmlspecialchars($row['area_name']) . "</span></a></li>\n";
      }
      $out_html .= "</ul>\n";
    }
    $out_html .= "</div>\n";
  }
  return $out_html;
} // end make_area_select_html


function make_room_select_html ($link, $area, $current, $year, $month, $day)
{
  global $tbl_room, $tbl_area, $area_list_format;
  
  $out_html = '';
  $sql = "SELECT R.id, R.room_name, R.description
            FROM $tbl_room R, $tbl_area A
           WHERE R.area_id=$area
             AND R.area_id=A.id
             AND R.disabled=0
             AND A.disabled=0
        ORDER BY R.sort_key";
  $res = sql_query($sql);
  // Only show the rooms if there's more than one of them, otherwise
  // there's no point
  if ($res && (sql_count($res) > 1))
  {
    $out_html .= "<div id=\"dwm_rooms\">\n";
    $out_html .= "<h3>" . get_vocab("rooms") . "</h3>";
    if ($area_list_format == "select")
    {
      $out_html .= "<form id=\"roomChangeForm\" method=\"get\" action=\"$link\">\n" .
                   "<div>\n" .
                   "<select class=\"room_area_select\" name=\"room\" onchange=\"this.form.submit()\">\n";
  
      for ($i = 0; ($row = sql_row_keyed($res, $i)); $i++)
      {
        $selected = ($row['id'] == $current) ? "selected=\"selected\"" : "";
        $out_html .= "<option $selected value=\"". $row['id']. "\" title=\"". htmlspecialchars($row['description'])."\">" . htmlspecialchars($row['room_name']) . "</option>\n";
      }
      // Note:  the submit button will not be displayed if JavaScript is enabled
      $out_html .= "</select>\n" .
                   "<input type=\"hidden\" name=\"day\"   value=\"$day\">\n" .
                   "<input type=\"hidden\" name=\"month\" value=\"$month\">\n" .
                   "<input type=\"hidden\" name=\"year\"  value=\"$year\">\n" .
                   "<input type=\"hidden\" name=\"area\"  value=\"$area\">\n" .
                   "<input type=\"submit\" class=\"js_none\" value=\"".get_vocab("change")."\">\n" .
                   "</div>\n" .
                   "</form>\n";
    }
    else  // list format
    {
      $out_html .= "<ul>\n";
      for ($i = 0; ($row = sql_row_keyed($res, $i)); $i++)
      {
        $out_html .= "<li><a href=\"$link?year=$year&amp;month=$month&amp;day=$day&amp;area=$area&amp;room=".$row['id']."\" title=\"". htmlspecialchars($row['description'])."\">";
        $out_html .= "<span" . (($row['id'] == $current) ? ' class="current"' : '') . ">";
        $out_html .= htmlspecialchars($row['room_name']) . "</span></a></li>\n";
      }
	  $out_html .= "<li><a href=\"month_allRooms.php?year=$year&amp;month=$month&amp;day=$day&amp;area=$area\" title=\"Toutes les salles\"><span>Toutes les salles (mois)</span></a></li>";
      $out_html .= "</ul>\n";
    }
	
    $out_html .= "</div>\n";
  }
  return $out_html;
} // end make_room_select_html


// returns the numeric day of the week (0-6) in terms of the MRBS week as defined by 
// $weekstarts.   For example if $weekstarts is set to 2 (Tuesday) and a $time for
// a Wednesday is given, then 1 is returned.
function day_of_MRBS_week($time)
{
  global $weekstarts;
  
  return (date('w', $time) - $weekstarts + 7) % 7;
}


// This will return the appropriate value for isdst for mktime().
// The order of the arguments was chosen to match those of mktime.
// hour is added so that this function can when necessary only be
// run if the time is between midnight and 3am (all DST changes
// occur in this period.
function is_dst($month, $day, $year, $hour="-1")
{
  if ( $hour != -1  && $hour > 3)
  {
    return( -1 );
  }
   
  // entering DST
  if( !date( "I", mktime(12, 0, 0, $month, $day-1, $year)) && 
      date( "I", mktime(12, 0, 0, $month, $day, $year)))
  {
    return( 0 ); 
  }

  // leaving DST
  else if( date( "I", mktime(12, 0, 0, $month, $day-1, $year)) && 
           !date( "I", mktime(12, 0, 0, $month, $day, $year)))
  {
    return( 1 );
  }
  else
  {
    return( -1 );
  }
}


// Compares two nominal dates which are indexed by'hours', 'minutes', 'seconds', 
// 'mon', 'mday' and 'year', ie as in the ouput of getdate().  Returns -1 if the
// first date is before the second, 0 if they are equal and +1 if the first date
// is after the second.   NULL if a comparison can't be done.
//
// (Note that internally the function uses gmmktime() so the parameters do not
// have to represent valid values.   For example you could pass '32' for day and
// that would be interpreted as 4 days after '28'.)
function nominal_date_compare($d1, $d2)
{
  // We compare the dates using gmmktime() because we are trying to compare nominal
  // dates and so do not want DST transitions
  $t1 = gmmktime($d1['hours'], $d1['minutes'], $d1['seconds'],
                 $d1['mon'], $d1['mday'], $d1['year']);
  $t2 = gmmktime($d2['hours'], $d2['minutes'], $d2['seconds'],
                 $d2['mon'], $d2['mday'], $d2['year']);
  if ($t1 < $t2)
  {
    return -1;
  }
  elseif ($t1 == $t2)
  {
    return 0;
  }
  else
  {
    return 1;
  }
}


// Determines whether there's a possibility that the interval between the two Unix 
// timestamps could contain nominal times that don't exist, for example from 0100 to
// 0159 in Europe/London when entering DST.
function is_possibly_invalid($start, $end)
{
  // We err on the side of caution by widening the interval by a day at each end.   This
  // allows for the possibility that the start or end times have been calculated by using
  // mktime() on an invalid time!
  return (cross_dst($start - 86400, $end + 86400) < 0);
}


// Checks whether the nominal time given is an invalid date and time with respect to
// DST transitions.   When entering DST there is a set of times that don't exist, for
// example from 0100 to 0159 in Europe/London.
// Returns NULL if MRBS is unable to determine an answer, otherwise TRUE or FALSE (so
// a simple equality test will default to a valid time if MRBS can't determine an answer)
function is_invalid_datetime($hour, $minute, $second, $month, $day, $year, $tz=NULL)
{
  global $timezone;

  // Do a quick check to see if there's a possibility of an invalid time by checking
  // whether there's a transition into DST from the day before to the day after
  if (!function_exists('date_default_timezone_set'))
  {
    return NULL;
  }
  
  if (empty($tz))
  {
    $tz = $timezone;  // default to the current timezone
  }
  
  $old_tz = date_default_timezone_get();  // save the current timezone
  date_default_timezone_set($tz);
  
  // If the day before is in DST then the datetime must be valid, because
  // you only get the gap when entering DST.
  $dayBefore = mktime($hour, $minute, $second, $month, $day-1, $year);
  if (date('I', $dayBefore))
  {
    $result = FALSE;
  }
  else
  {
    // The day before is not in DST.   If the day after is also not in DST,
    // then there can have been no transition, so again the datetime must be valid.
    $dayAfter = mktime($hour, $minute, $second, $month, $day+1, $year);
    if (!date('I', $dayAfter))
    {
      $result = FALSE;
    }
    else
    {
      // We are in a transition into DST, so we need to check more carefully.
      // However we can only do this efficiently in PHP 5.3.0 or greater
      if (version_compare(PHP_VERSION, '5.3.0') < 0)
      {
        $result = NULL;
      }
      else
      {
        $thisDateTimeZone = new DateTimeZone($tz);
        // Get the transition data (we assume there is one and only one transition),
        // in particular the time at which the transition happens and the new offset
        $transitions = $thisDateTimeZone->getTransitions($dayBefore, $dayAfter);
        // According to my reading of the PHP manual, getTransitions() should return
        // all transitions between the start and end date.   However what it seems to do
        // is return an array consisting of the time data for the start date followed by
        // the transition data.   So as a precaution we take the last element of the array
        // (we were only expecting one element, but seem to get two).
        $transition = array_pop($transitions);
        // If we failed for some reason to get any transition data, return NULL
        if (!isset($transition))
        {
          $result = NULL;
        }
        else
        {
          // Get the old offset and work out how many seconds the clocks change by
          $beforeDateTime = new DateTime(date('c', $dayBefore), $thisDateTimeZone);
          $change = $transition['offset'] - $beforeDateTime->getOffset();
  
          // See if the nominal date falls outside the gap
          $lastValidSecond = getdate($transition['ts'] - 1);
          $lastInvalidSecond = $lastValidSecond;
          $lastInvalidSecond['seconds'] += $change;
          $thisDate = array('hours' => $hour, 'minutes' => $minute, 'seconds' => $second,
                            'mon' => $month, 'mday' => $day, 'year' => $year);
                  
          $result = ((nominal_date_compare($thisDate, $lastValidSecond) > 0) && 
                     (nominal_date_compare($thisDate, $lastInvalidSecond) <= 0));
        }
      }
    }
  }
  
  date_default_timezone_set($old_tz);  // restore the old timezone
  
  return $result;
}


// Returns TRUE if $t is in that part of daylight saving time that will have
// the same nominal time as another timestamp shortly after the DST transition.
// For example, will return TRUE for the $t equivalent to the first occurence
// of 0230 on Sunday 27th October 2013 in timezone Europe/London
function is_dst_first_duplicate($t)
{
  if (date('I', $t))
  {
    $change = cross_dst($t, $t+SECONDS_PER_DAY);
    return !date('I', $t + $change);
  }
  return false;
}


// A PHP version independent version of mktime().   (The $is_dst parameter
// became deporecated at PHP 5.1.0)
function mrbs_mktime($hour, $minute, $second, $month, $day, $year)
{
  if (version_compare(PHP_VERSION, '5.1.0') >= 0)
  {
    return mktime($hour, $minute, $second, $month, $day, $year);
  }
  else
  {
    return mktime($hour, $minute, $second, $month, $day, $year,
                  is_dst($month, $day, $year, $hour));
  }
}

// returns the modification (in seconds) necessary on account of any DST
// transitions when going from $start to $end
function cross_dst($start, $end)
{
  global $timezone;
  
  // Ideally we calculate the modification using the DateTimeZone information
  // in PHP, because not all DST transitions are 1 hour.  For example, Lord Howe
  // Island in Australia has a 30 minute transition
  if (class_exists('DateTimeZone'))
  {
    $thisDateTimeZone = new DateTimeZone($timezone);
    $startDateTime = new DateTime(date('c', $start), $thisDateTimeZone);
    $endDateTime = new DateTime(date('c', $end), $thisDateTimeZone);
    $modification = $startDateTime->getOffset() - $endDateTime->getOffset();
  }
  // Otherwise we have to assume that the transition is 1 hour.
  else
  {
    // entering DST
    if (!date( "I", $start) &&  date( "I", $end))
    {
      $modification = -SECONDS_PER_HOUR;
    }
    // leaving DST
    else if (date( "I", $start) && !date( "I", $end))
    {
      $modification = SECONDS_PER_HOUR;
    }
    else
    {
      $modification = 0;
    }
  }

  return $modification;
}

// If $time falls on a non-working day, shift it back to the end of the last 
// working day before that
function shift_to_workday($time)
{
  global $working_days;
  
  $dow = date('w', $time);  // get the day of the week
  $skip_back = 0;           // number of days to skip back
  // work out how many days to skip back to get to a working day
  while (!in_array($dow, $working_days))
  {
    if ($skip_back == 7)
    {
      break;
    }
    $skip_back++;
    $dow = ($dow + 6) % 7;  // equivalent to skipping back a day
  }
  if ($skip_back != 0)
  {
    // set the time to the end of the working day
    $d = date('j', $time) - $skip_back;
    $m = date('n', $time);
    $y  = date('Y', $time);
    $time = mktime(23, 59, 59, $m, $d, $y);
  }
  return $time;
}
  
// Returns the difference in seconds between two timestamps, $now and $then
// It gives $now - $then, less any seconds that were part of a non-working day
function working_time_diff($now, $then)
{
  global $working_days;
  
  // Deal with the easy case
  if ($now == $then)
  {
    return 0;
  }
  // Sanitise the $working_days array in case it was malformed
  $working_week = array_unique(array_intersect(array(0,1,2,3,4,5,6), $working_days));
  $n_working_days = count($working_week);
  // Deal with the special case where there are no working days
  if ($n_working_days == 0)
  {
    return 0;
  }
  // and the special case where there are no holidays
  if ($n_working_days == 7)
  {
    return ($now - $then);
  }

  // For the rest we're going to assume that $last comes after $first
  $last = max($now, $then);
  $first = min($now, $then);
  
  // first of all, if $last or $first fall on a non-working day, shift
  // them back to the end of the last working day
  $last = shift_to_workday($last);
  $first = shift_to_workday($first);
  // So calculate the difference
  $diff = $last - $first;
  // Then we have to deduct all the non-working days in between.   This will be
  // (a) the number of non-working days in the whole weeks between them +
  // (b) the number of non-working days in the part week
  
  // First let's calculate (a)
  $last = mktime(12, 0, 0, date('n', $last), date('j', $last), date('Y', $last));
  $first = mktime(12, 0, 0, date('n', $first), date('j', $first), date('Y', $first));
  $days_diff = (int) round(($last - $first)/SECONDS_PER_DAY);  // the difference in days
  $whole_weeks = (int) floor($days_diff/7);  // the number of whole weeks between the two
  $non_working_days = $whole_weeks * (7 - $n_working_days);
  // Now (b), ie we just have to calculate how many non-working days there are between the two
  // days of the week that are left
  $last_dow = date('w', $last);
  $first_dow = date('w', $first);
  
  while ($first_dow != $last_dow)
  {
    $first_dow = ($first_dow + 1) % 7;
    if (!in_array($first_dow, $working_week))
    {
      $non_working_days++;
    }
  }

  // So now subtract the number of weekend seconds
  $diff = $diff - ($non_working_days * SECONDS_PER_DAY);
  
  // Finally reverse the difference if $now was in fact before $then
  if ($now < $then)
  {
    $diff = -$diff;
  }
  
  return (int) $diff;
}

// checks whether a given day of the week is supposed to be hidden in the display
function is_hidden_day ($dow)
{
  global $hidden_days;
  return (isset($hidden_days) && in_array($dow, $hidden_days));
}

// returns true if event should be considered private based on
// config settings and event's privacy status (passed to function)
function is_private_event($privacy_status) 
{
  global $private_override;
  if ($private_override == "private" )
  {
    $privacy_status = TRUE;
  }
  elseif ($private_override == "public" )
  {
    $privacy_status = FALSE;
  }

  return $privacy_status;
}

// Generate a globally unique id
//
// We will generate a uid of the form "MRBS-uniqid-MD5hash@domain_name" 
// where uniqid is time based and is generated by uniqid() and the
// MD5hash is the first 8 characters of the MD5 hash of $str concatenated
// with a random number.
function generate_global_uid($str)
{
  $uid = uniqid('MRBS-');
  $uid .= "-" . substr(md5($str . rand(0,10000)), 0, 8);
  $uid .= "@";
  // Add on the domain name if possible, if not the server name,
  // otherwise 'MRBS'
  if (empty($_SERVER['SERVER_NAME']))
  {
    $uid .= 'MRBS';
  }
  elseif (strpos($_SERVER['SERVER_NAME'], 'www.') === 0)
  {
    $uid .= utf8_substr($_SERVER['SERVER_NAME'], 4);
  }
  else
  {
    $uid .= $_SERVER['SERVER_NAME'];
  }

  return $uid;
}

// Tests whether an array is associative
//
// Thanks to magentix at gmail dot com at http://php.net/manual/function.is-array.php
function is_assoc($arr)
{
  return (is_array($arr) && count(array_filter(array_keys($arr),'is_string')) == count($arr));
}


// Checks whether we are running as a CLI module
//
// Based on code from mniewerth at ultimediaos dot com at 
// http://php.net/manual/features.commandline.php
function is_cgi()
{
  return (substr(PHP_SAPI, 0, 3) == 'cgi');
}


// Checks whether we are running from the CLI
//
// Based on code from mniewerth at ultimediaos dot com at 
// http://php.net/manual/features.commandline.php
function is_cli()
{
  global $allow_cli;
  
  if (!$allow_cli)
  {
    return FALSE;
    
  }
  if (defined('STDIN'))
  {
    return TRUE;
  }
  elseif (is_cgi() && getenv('TERM'))
  {
    return TRUE;
  }
  else
  {
    return FALSE;
  }
}
//fonctions persos ajoutées
//retrouve les valeurs des champs enum de la table entry
function get_enum_values( $field ) 
{
    
    if ($field == 'type action')
    {
        $enum = array('accompagnement de projet','animation','formation continue','formation initiale','réunion avec GT','évènement');
    }
    if ($field == 'publics')
    {
        $enum = array('élèves','étudiants','cadres','1er degré','2d degré','interdegré','autre');
    }
    if ($field == 'modalité')
    {
        $enum = array('atelier','conférence','séminaire','salon','atelier en mode codesign','conseil et expertise','sans objet');
    }
    if ($field == 'intervention_canope')
    {
        $enum = array('non','1h','2h','3h','journée');
    }
    if ($field == 'classe_nomade')
    {
        $enum = array('non','mac','pc','indifférent');
    }
    if ($field == 'axes de Versailles 2020')
    {
        $enum = array('axe 1 - les nouveaux enseignants','axe 5 - la réforme du collège','axe 2 - le travail collaboratif','axe 3 - les langues','axe 4 - les enseignements pratiques interdisciplinaires','axe 6 - le parcours citoyen','axe 7 - le parcours éducation artistique et culturelle','axe 8 - le parcours Avenir','axe 9 - développer l esprit critique','axe 10 - la voie professionnelle','axe 11 - les élèves à besoins particuliers','axe 12 - éducation à la santé');
    }
    return $enum;

}

function get_enum_values_old( $field )
{
	//$sql = "SHOW COLUMNS FROM mrbs_entry LIKE $field";
  
  $sql = "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = \'mrbs_entry\' AND COLUMN_NAME = \'".$field."\'";
  echo("<script>console.log('sql : ".$sql."');</script>");
  $result = sql_query($sql);
  echo("<script>console.log('PHP: ".gettype($result)."');</script>");
  $row = mysql_fetch_array($result , MYSQL_NUM );
  echo("<script>console.log('PHP: ".count($row)."');</script>");
  $type = $row[1];
	
	preg_match('/enum\((.*)\)$/', $type, $matches);
    foreach( explode(',', $matches[1]) as $value )
    {
         $enum[] = trim( $value, "'" );
    }
    return $enum;
}
function enumDropdown($column_name, $echo = false)
{
   $selectDropdown = "<select name=\"$column_name\">";
   $result = sql_query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
       WHERE TABLE_NAME = 'mrbs_entry' AND COLUMN_NAME = '$column_name'")
   or die (mysql_error());

    $row = mysql_fetch_array($result);
    $enumList = explode(",", str_replace("'", "", substr($row['COLUMN_TYPE'], 5, (strlen($row['COLUMN_TYPE'])-6))));

    foreach($enumList as $value)
         $selectDropdown .= "<option value=\"$value\">$value</option>";

    $selectDropdown .= "</select>";

    if ($echo)
        echo $selectDropdown;

    return $selectDropdown;
}
function get_enum_values1( $field )
{
    $sql = "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'mrbs_entry' AND COLUMN_NAME = $field";
  $result = sql_query($sql);
    $type = $this->db->query( "SHOW COLUMNS FROM 'mrbs_entry' WHERE Field = '{$field}'" )->row( 0 )->Type;
    preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches);
    $enum = explode("','", $matches[1]);
    return $enum;
}

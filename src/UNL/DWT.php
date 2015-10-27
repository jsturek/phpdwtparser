<?php
/**
 * Base class which understands Dreamweaver Templates.
 *
 * @category  Templates
 * @package   UNL_DWT
 * @author    Brett Bieber <brett.bieber@gmail.com>
 * @copyright 2015 Regents of the University of Nebraska
 * @license   http://wdn.unl.edu/software-license BSD License
 * @link      https://github.com/unl/phpdwtparser
 */

class UNL_DWT
{
    const TEMPLATE_TOKEN = 'Template';
    const INSTANCE_TOKEN = 'Instance';

    const REGION_BEGIN_TOKEN = '<!-- %sBeginEditable name="%s" -->';
    const REGION_END_TOKEN   = '<!-- %sEndEditable -->';

    const PARAM_DEF_TOKEN         = '<!-- %sParam name="%s" type="%s" value="%s" -->';
    const PARAM_REPLACE_TOKEN     = '@@(%s)@@';
    const PARAM_REPLACE_TOKEN_ALT = '@@(_document[\'%s\'])@@';

    public $__template;
    public $__params = array();

    /**
     * Run-time configuration options
     *
     * @var array
     * @see UNL_DWT::setOption()
     */
    public static $options = array(
        'debug' => 0,
    );

    /**
     * Returns a string that contains the template file.
     *
     * @return string
     */
    public function getTemplateFile()
    {
        if (!isset($this->__template) || empty(self::$options['tpl_location'])) {
            return '';
        }

        return file_get_contents(self::$options['tpl_location'].$this->__template);
    }

    public function getRegions()
    {
        $regions = get_object_vars($this);
        foreach (array_keys($regions) as $key) {
            if (strpos($key, '__') === 0) {
                unset($regions[$key]);
            }
        }

        return $regions;
    }

    public function getParams()
    {
        return $this->__params;
    }

    public function setParam($key, $value)
    {
        if (!isset($$this->__params[$key])) {
            return $this;
        }

        $this->__params[$key]['value'] = $value;

        return $this;
    }

    /**
     * Returns the given DWT with all regions replaced with their assigned
     * content.
     *
     * @return string
     */
    public function toHtml()
    {
        $p = $this->getTemplateFile();
        $regions = $this->getRegions();
        $params = $this->getParams();

        $p = $this->replaceRegions($p, $regions);
        $p = $this->replaceParams($p, $params);

        return $p;
    }

    /**
     * @see $this->toHtml
     * @return string
     */
    public function __toString()
    {
        return $this->toHtml();
    }

    public function getRegionBeginMarker($type, $region)
    {
        return sprintf(self::REGION_BEGIN_TOKEN, $type, $region);
    }

    public function getRegionEndMarker($type)
    {
        return sprintf(self::REGION_END_TOKEN, $type);
    }

    public function getParamDefMarker($type, $name, $paramType, $value)
    {
        return sprintf(self::PARAM_DEF_TOKEN, $type, $name, $paramType, $value);
    }

    public function getParamReplacePattern($name)
    {
        return '/' . sprintf(
            self::PARAM_DEF_TOKEN,
            '(' . self::TEMPLATE_TOKEN . '|' . self::INSTANCE_TOKEN . ')',
            $name,
            '([^"]*)',
            '[^"]*'
        ) . '/';
    }

    public function getParamNeedle($name)
    {
        return array(
            sprintf(self::PARAM_REPLACE_TOKEN, $name),
            sprintf(self::PARAM_REPLACE_TOKEN_ALT, $name)
        );
    }

    /**
     * Replaces region tags within a template file wth their contents.
     *
     * @param string $p       Page with DW Region tags.
     * @param array  $regions Associative array with content to replace.
     *
     * @return string page with replaced regions
     */
    public function replaceRegions($p, $regions)
    {
        self::debug('Replacing regions.', 'replaceRegions', 5);

        foreach ($regions as $region => $value) {
            /* Replace the region with the replacement text */
            $startMarker = $this->getRegionBeginMarker(self::TEMPLATE_TOKEN, $region);
            $endMarker = $this->getRegionEndMarker(self::TEMPLATE_TOKEN);
            $p = str_replace(
                self::strBetween($startMarker, $endMarker, $p, true),
                $startMarker . $value . $endMarker,
                $p,
                $count
            );

            if (!$count) {
                $startMarker = $this->getRegionBeginMarker(self::INSTANCE_TOKEN, $region);
                $endMarker = $this->getRegionEndMarker(self::INSTANCE_TOKEN);
                $p = str_replace(
                    self::strBetween($startMarker, $endMarker, $p, true),
                    $startMarker . $value . $endMarker,
                    $p,
                    $count
                );
            }

            if (!$count) {
                self::debug("Counld not find region $region!", 'replaceRegions', 3);
            } else {
                self::debug("$region is replaced with $value.", 'replaceRegions', 5);
            }
        }
        return $p;
    }

    public function replaceParams($p, $params)
    {
        self::debug('Replacing params.', 'replaceRegions', 5);

        foreach ($params as $name => $config) {
            $value = isset($config['value']) ? $config['value'] : '';
            $p = preg_replace(
                $this->getParamReplacePattern($name),
                $this->getParamDefMarker('$1', $name, '$2', $value),
                $p,
                1,
                $count
            );

            if ($count) {
                $p = str_replace($this->getParamNeedle($name), $value, $p);
            }
        }

        return $p;
    }

    /**
     * Create a new UNL_DWT object for the specified layout type
     *
     * @param string $type     the template type (eg "fixed")
     * @param array  $coptions an associative array of option names and values
     *
     * @return object  a new UNL_DWT.  A UNL_DWT_Error object on failure.
     *
     * @see UNL_DWT::setOption()
     */
    public static function factory($type)
    {
        $classname = self::$options['class_prefix'] . $type;

        if (!class_exists($classname)) {
            throw new UNL_DWT_Exception("Unable to find the $classname class");
        }

        @$obj = new $classname;

        return $obj;
    }

    /**
     * Sets options.
     *
     * @param string $option Option to set
     * @param mixed  $value  Value to set for this option
     *
     * @return void
     */
    public static function setOption($option, $value)
    {
        self::$options[$option] = $value;
    }

    /* ----------------------- Debugger ------------------ */

    /**
     * Debugger. - use this in your extended classes to output debugging
     * information.
     *
     * Uses UNL_DWT::debugLevel(x) to turn it on
     *
     * @param string $message message to output
     * @param string $logtype bold at start
     * @param string $level   output level
     *
     * @return   none
     */
    public static function debug($message, $logtype = 0, $level = 1)
    {
        if (empty(self::$options['debug'])  ||
            (is_numeric(self::$options['debug']) &&  self::$options['debug'] < $level)) {
            return;
        }
        // this is a bit flaky due to php's wonderfull class passing around crap..
        // but it's about as good as it gets..
        $class = (isset($this) && ($this instanceof UNL_DWT)) ? get_class($this) : 'UNL_DWT';

        if (!is_string($message)) {
            $message = print_r($message, true);
        }
        if (!is_numeric(self::$options['debug']) && is_callable(self::$options['debug'])) {
            return call_user_func(self::$options['debug'], $class, $message, $logtype, $level);
        }

        if (!ini_get('html_errors')) {
            echo "$class   : $logtype       : $message\n";
            flush();
            return;
        }
        if (!is_string($message)) {
            $message = print_r($message, true);
        }
        $colorize = ($logtype == 'ERROR') ? '<font color="red">' : '<font>';
        echo "<code>{$colorize}<strong>$class: $logtype:</strong> " .
            nl2br(htmlspecialchars($message)) .
            "</font></code><br />\n";
        flush();
    }

    /**
     * sets and returns debug level
     * eg. UNL_DWT::debugLevel(4);
     *
     * @param int $v level
     *
     * @return void
     */
    public static function debugLevel($v = null)
    {
        if ($v !== null) {
            $r = isset(self::$options['debug']) ? self::$options['debug'] : 0;
            self::$options['debug']  = $v;
            return $r;
        }
        return isset(self::$options['debug']) ? self::$options['debug'] : 0;
    }

    /**
     * Returns content between two strings
     *
     * @param string $start String which bounds the start
     * @param string $end   end collecting content when you see this
     * @param string $p     larger body of content to search
     *
     * @return string
     */
    public static function strBetween($start, $end, $p, $inclusive = false)
    {
        if (!empty($start) && strpos($p, $start) !== false) {
            $p = substr($p, strpos($p, $start)+($inclusive ? 0 : strlen($start)));
        } else {
            return '';
        }

        if (strpos($p, $end) !==false) {
            $p = substr($p, 0, strpos($p, $end)+($inclusive ? strlen($end) : 0));
        } else {
            return '';
        }
        return $p;
    }
}

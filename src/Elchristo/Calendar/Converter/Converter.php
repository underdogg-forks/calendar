<?php

namespace Elchristo\Calendar\Converter;

use Elchristo\Calendar\Exception\RuntimeException;
use Elchristo\Calendar\Model\CalendarInterface;
use Elchristo\Calendar\Converter\ConverterInterface;

/**
 * Facade class to access converters and execute conversion process
 */
class Converter
{
    /** @var array Cached converter instances */
    private static $converters = [];

    /**
     * Convert events of given calendar into given converter (name or classname)
     *
     * @param CalendarInterface $calendar Calendar to convert
     * @param string $name                Converter (class)name
     * @param array  $options             Additional options
     *
     * @return mixed
     * @throws RuntimeException
     */
    public static function convert(CalendarInterface $calendar, $name, array $options = [])
    {
        if (\false === $canonicalizeName = self::exists($name, $calendar->getConfig()->getRegisteredConverters(), \true)) {
            throw new RuntimeException(\sprintf('Converter with name "%s" was not found (neither in configuration nor by class name resolving).', $name));
        }

        $converter = self::$converters[$canonicalizeName];
        return $converter->convert($calendar, $options);
    }

    /**
     * Checks if a converter exists
     *
     * @param string  $name              Converter name (in config) or classname
     * @param array   $convertibleEvents List of configured convertible events
     * @param boolean $buildIfExists     Build converter instance if found and not already in cache
     *
     * @return mixed canonicalized name if converter exists, otherwise FALSE
     */
    protected static function exists(string $name, array $convertibleEvents = [], $buildIfExists = \false)
    {
        $canonicalizeName = self::canonicalizeName($name);
        if (isset(self::$converters[$canonicalizeName])) {
            return $canonicalizeName;
        } else if (\is_subclass_of($name, ConverterInterface::class)) {
            // passed classname
            if ($buildIfExists === \true) {
                self::build($name, $convertibleEvents);
            }
            return $canonicalizeName;
        } else if (\array_key_exists($name, $convertibleEvents) && \is_array($convertibleEvents[$name])) {
            // converter name declared in configuration
            $classname = \current($convertibleEvents[$name]);
            if ($buildIfExists === \true) {
                self::build($classname, $convertibleEvents);
            }
            return self::canonicalizeName($classname);
        } else {
            // lookup in default namespace
            $className = __NAMESPACE__ . '\\' . \ucfirst($name) . '\\' . \ucfirst($name);
            if (\true === \is_subclass_of($className, ConverterInterface::class)) {
                if ($buildIfExists === \true) {
                    self::build($className, $convertibleEvents);
                }
                return self::canonicalizeName($className);
            }
        }

        return \false;
    }

    /**
     * Build converter instance by classname and put it into internal cache array
     *
     * @param string $className
     * @param array  $registeredConvertibleEvents List of configured converters
     * @return ConverterInterface
     */
    private static function build(string $className, array $registeredConvertibleEvents = [])
    {
        $canonicalizeName = self::canonicalizeName($className);
        if (isset(self::$converters[$canonicalizeName])) {
            return self::$converters[$canonicalizeName];
        }

        $eventBuilder = new ConvertibleEventFactory($registeredConvertibleEvents);
        $converter = new $className($eventBuilder);
        self::$converters[$canonicalizeName] = $converter;
        return $converter;
    }

    private static function canonicalizeName($name)
    {
        return \strtolower(\strtr($name, [ '-' => '', '_' => '', ' ' => '', '\\' => '', '/' => '' ]));
    }
}

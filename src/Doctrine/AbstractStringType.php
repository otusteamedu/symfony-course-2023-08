<?php

namespace App\Doctrine;

use App\Domain\ValueObject\AbstractValueObjectString;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

abstract class AbstractStringType extends Type
{
    abstract protected function getConcreteValueObjectType(): string;

    public function convertToPHPValue($value, AbstractPlatform $platform): ?AbstractValueObjectString
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            /** @var AbstractValueObjectString $concreteValueObjectType */
            $concreteValueObjectType = $this->getConcreteValueObjectType();

            return $concreteValueObjectType::fromString($value);
        }

        /** @psalm-suppress MixedArgument */
        throw ConversionException::conversionFailed($value, $this->getName());
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof AbstractValueObjectString) {
            return $value->getValue();
        }

        /** @psalm-suppress MixedArgument */
        throw ConversionException::conversionFailed($value, $this->getName());
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }
}

<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Bundle\Storage\Sql\Connector\Writer\File\Flat;

use Akeneo\Pim\Enrichment\Component\Product\Connector\Writer\File\FlatFileHeader;
use Akeneo\Pim\Enrichment\Component\Product\Connector\Writer\File\GenerateFlatHeadersFromFamilyCodesInterface;
use Akeneo\Pim\Structure\Component\AttributeTypes;
use Doctrine\DBAL\Connection;

/**
 * @author    Benoit Jacquemont <benoit@akeneo.com>
 * @copyright 2018 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
final class GenerateHeadersFromFamilyCodes implements GenerateFlatHeadersFromFamilyCodesInterface
{
    /** @var Connection */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Generate all possible headers from the provided family codes
     *
     * @return FlatFileHeader[]
     */
    public function __invoke(
        array $familyCodes,
        string $channelCode,
        array $localeCodes
    ): array {
        $activatedCurrencyCodes = $this->connection->executeQuery(
            "SELECT code FROM pim_catalog_currency WHERE is_activated = 1"
        )->fetchAll(\PDO::FETCH_COLUMN, 0);

        $channelCurrencyCodesSql = <<<SQL
            SELECT currency.code
            FROM pim_catalog_channel channel
              JOIN pim_catalog_channel_currency cc ON cc.channel_id = channel.id
              JOIN pim_catalog_currency currency ON currency.id = cc.currency_id
            WHERE channel.code = :channelCode
SQL;
        $channelCurrencyCodes = $this->connection->executeQuery(
            $channelCurrencyCodesSql,
            ['channelCode' => $channelCode]
        )->fetchAll(\PDO::FETCH_COLUMN, 0);

        $attributesDataSql = <<<SQL
            SELECT a.code,
                   a.is_scopable,
                   a.is_localizable,
                   a.attribute_type,
                   (
                     SELECT JSON_ARRAYAGG(l.code)
                     FROM pim_catalog_locale l
                     JOIN pim_catalog_attribute_locale al ON al.locale_id = l.id
                     WHERE al.attribute_id = a.id
                   ) AS specific_to_locales
            FROM pim_catalog_family f
              JOIN pim_catalog_family_attribute fa ON fa.family_id = f.id
              JOIN pim_catalog_attribute a ON a.id = fa.attribute_id
            WHERE f.code IN (:familyCodes)
            GROUP BY a.id;
SQL;

        $rawAttributesData = $this->connection->executeQuery(
            $attributesDataSql,
            ['familyCodes' => $familyCodes],
            ['familyCodes' => \Doctrine\DBAL\Connection::PARAM_STR_ARRAY]
        )->fetchAll();

        $mediaAttributeTypes = [
            AttributeTypes::IMAGE,
            AttributeTypes::FILE
        ];
        $headers = [];
        foreach ($rawAttributesData as $rawAttributeData) {
            $headers[] = new FlatFileHeader(
                $rawAttributeData["code"],
                ("1" === $rawAttributeData["is_scopable"]),
                $channelCode,
                ("1" === $rawAttributeData["is_localizable"]),
                $localeCodes,
                (in_array($rawAttributeData['attribute_type'], $mediaAttributeTypes)),
                (AttributeTypes::METRIC === $rawAttributeData['attribute_type']),
                (AttributeTypes::PRICE_COLLECTION === $rawAttributeData['attribute_type']),
                $channelCurrencyCodes,
                $activatedCurrencyCodes,
                (null !== $rawAttributeData['specific_to_locales']),
                null !== $rawAttributeData['specific_to_locales'] ? json_decode($rawAttributeData['specific_to_locales'], true) : []
            );
        }

        return $headers;
    }
}

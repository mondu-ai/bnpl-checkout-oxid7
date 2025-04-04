<?php

namespace OxidEsales\MonduPayment\Model;

class MonduPayment extends MonduPayment_parent
{
    const MONDU_INVOICE = 'oxmondu_invoice';
    const MONDU_DIRECT_DEBIT = 'oxmondu_direct_debit';
    const MONDU_INSTALLMENT = 'oxmondu_installment';

    public const MONDU_PAYMENT_METHODS = [
        self::MONDU_INVOICE => [
            'payment_id' => 'oxmondu_invoice',
            'mondu_payment_method' => 'invoice',
            'name' => 'Mondu | Rechnungskauf - Später per Banküberweisung bezahlen',
            'description' => 'Hinweise zur Verarbeitung Ihrer personenbezogenen Daten durch die Mondu GmbH finden Sie <a href=https://www.mondu.ai/de/datenschutzgrundverordnung-kaeufer/ target="_blank">hier</a>.',
            'translations' => [
                'de' => [
                    'name' => 'Mondu | Rechnungskauf - Später per Banküberweisung bezahlen',
                    'description' => 'Hinweise zur Verarbeitung Ihrer personenbezogenen Daten durch die Mondu GmbH finden Sie <a href=https://www.mondu.ai/de/datenschutzgrundverordnung-kaeufer/ target="_blank">hier</a>.',
                ],
                'en' => [
                    'name' => 'Mondu | Invoice - Pay later by bank transfer',
                    'description' => 'Information on the processing of your personal data by Mondu GmbH can be found <a href=https://www.mondu.ai/de/datenschutzgrundverordnung-kaeufer/ target="_blank">here</a>.',
                ],
                'nl' => [
                    'name' => 'Mondu | Factuur - Aankoop op rekening - nu kopen, later betalen',
                    'description' => 'Informatie over de verwerking van uw persoonsgegevens door Mondu GmbH vindt u <a href=https://www.mondu.ai/nl/information-nach-art-13-datenschutzgrundverordnung-fur-kaufer/ target="_blank">hier</a>.',
                ]
            ],
        ],
        self::MONDU_DIRECT_DEBIT => [
            'payment_id' => 'oxmondu_direct_debit',
            'mondu_payment_method' => 'direct_debit',
            'name' => 'Mondu | SEPA - Später zahlen per Bankeinzug',
            'description' => 'Hinweise zur Verarbeitung Ihrer personenbezogenen Daten durch die Mondu GmbH finden Sie <a href=https://www.mondu.ai/de/datenschutzgrundverordnung-kaeufer/ target="_blank">hier</a>.',
            'translations' => [
                'de' => [
                    'name' => 'Mondu | SEPA - Später zahlen per Bankeinzug',
                    'description' => 'Hinweise zur Verarbeitung Ihrer personenbezogenen Daten durch die Mondu GmbH finden Sie <a href=https://www.mondu.ai/de/datenschutzgrundverordnung-kaeufer/ target="_blank">hier</a>.',
                ],
                'en' => [
                    'name' => 'Mondu | SEPA - Pay later by direct debit',
                    'description' => 'Information on the processing of your personal data by Mondu GmbH can be found <a href=https://www.mondu.ai/de/datenschutzgrundverordnung-kaeufer/ target="_blank">here</a>.',
                ],
                'nl' => [
                    'name' => 'Mondu | SEPA automatische incasso - nu kopen, later betalen',
                    'description' => 'Informatie over de verwerking van uw persoonsgegevens door Mondu GmbH vindt u <a href=https://www.mondu.ai/nl/information-nach-art-13-datenschutzgrundverordnung-fur-kaufer/ target="_blank">hier</a>.',
                ]
            ],
        ],
        self::MONDU_INSTALLMENT => [
            'payment_id' => 'oxmondu_installment',
            'mondu_payment_method' => 'installment',
            'name' => 'Mondu | Ratenkauf - Bequem in Raten per Bankeinzug zahlen',
            'description' => 'Hinweise zur Verarbeitung Ihrer personenbezogenen Daten durch die Mondu GmbH finden Sie <a href=https://www.mondu.ai/de/datenschutzgrundverordnung-kaeufer/ target="_blank">hier</a>.',
            'translations' => [
                'de' => [
                    'name' => 'Mondu | Ratenkauf - Bequem in Raten per Bankeinzug zahlen',
                    'description' => 'Hinweise zur Verarbeitung Ihrer personenbezogenen Daten durch die Mondu GmbH finden Sie <a href=https://www.mondu.ai/de/datenschutzgrundverordnung-kaeufer/ target="_blank">hier</a>.',
                ],
                'en' => [
                    'name' => 'Mondu | Split payments - Pay conveniently in instalments by direct debit',
                    'description' => 'Information on the processing of your personal data by Mondu GmbH can be found <a href=https://www.mondu.ai/de/datenschutzgrundverordnung-kaeufer/ target="_blank">here</a>.',
                ],
                'nl' => [
                    'name' => 'Mondu | Gespreid betalen - Betaal gemakkelijk in termijnen via automatische incasso',
                    'description' => 'Informatie over de verwerking van uw persoonsgegevens door Mondu GmbH vindt u <a href=https://www.mondu.ai/nl/information-nach-art-13-datenschutzgrundverordnung-fur-kaufer/ target="_blank">hier</a>.',
                ]
            ],
        ]
    ];

    public static function getMonduPaymentMethodFromPaymentId($paymentId)
    {
        $idx = array_search($paymentId, array_column(self::MONDU_PAYMENT_METHODS, 'payment_id'));

        if ($idx) {
            return array_values(self::MONDU_PAYMENT_METHODS)[$idx];
        }

        return null;
    }
}

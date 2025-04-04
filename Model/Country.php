<?php

namespace OxidEsales\MonduPayment\Model;

use OxidEsales\Eshop\Core\DatabaseProvider;

class Country extends Country_parent
{
    public function getCodeById($id)
    {
        $oDb = DatabaseProvider::getDb();

        return $oDb->getOne("SELECT oxisoalpha2 FROM oxcountry WHERE oxid = :oxid", [':oxid' => $id]);
    }
}

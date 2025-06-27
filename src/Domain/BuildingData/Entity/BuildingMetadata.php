<?php

declare(strict_types=1);

namespace App\Domain\BuildingData\Entity;

use App\Domain\BuildingData\Repository\BuildingMetadataRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'building_metadata')]
#[ORM\Entity(repositoryClass: BuildingMetadataRepository::class)]
class BuildingMetadata
{
    /**
     * Eidgenössischer Gebäudeidentifikator (Federal Building Identifier)
     */
    #[ORM\Id]
    #[ORM\Column(name: 'egid', length: 9)]
    public string $egid;

    /**
     * Kantonskürzel (Canton)
     */
    #[ORM\Column(name: 'gdekt', length: 2)]
    public string $gdekt;

    /**
     * BFS-Gemeindenummer (Municipality Code)
     */
    #[ORM\Column(name: 'ggdenr', length: 4)]
    public string $ggdenr;

    /**
     * Gemeindename (Municipality Name)
     */
    #[ORM\Column(name: 'ggdename', length: 40)]
    public string $ggdename;

    /**
     * Eidgenössischer Grundstücksidentifikator (Federal Property Identifier)
     */
    #[ORM\Column(name: 'egrid', length: 14)]
    public string $egrid;

    /**
     * Grundbuchkreisnummer (Land Registry District Number)
     */
    #[ORM\Column(name: 'lgbkr', length: 4)]
    public string $lgbkr;

    /**
     * Grundstücksnummer (Property Number)
     */
    #[ORM\Column(name: 'lparz', length: 12)]
    public string $lparz;

    /**
     * Suffix der Grundstücksnummer (Property Number Suffix)
     */
    #[ORM\Column(name: 'lparzsx', length: 12)]
    public string $lparzsx;

    /**
     * Typ des Grundstücks (Property Type)
     */
    #[ORM\Column(name: 'ltyp', length: 4)]
    public string $ltyp;

    /**
     * Amtliche Gebäudenummer (Official Building Number)
     */
    #[ORM\Column(name: 'gebnr', length: 12)]
    public string $gebnr;

    /**
     * Name des Gebäudes (Building Name)
     */
    #[ORM\Column(name: 'gbez', length: 40)]
    public string $gbez;

    /**
     * E-Gebäudekoordinate (East Building Coordinate)
     */
    #[ORM\Column(name: 'gkode', length: 11)]
    public string $gkode;

    /**
     * N-Gebäudekoordinate (North Building Coordinate)
     */
    #[ORM\Column(name: 'gkodn', length: 11)]
    public string $gkodn;

    /**
     * Koordinatenherkunft (Coordinate Source)
     */
    #[ORM\Column(name: 'gksce', length: 3)]
    public string $gksce;

    /**
     * Gebäudestatus (Building Status)
     */
    #[ORM\Column(name: 'gstat', length: 4)]
    public string $gstat;

    /**
     * Gebäudekategorie (Building Category)
     */
    #[ORM\Column(name: 'gkat', length: 4)]
    public string $gkat;

    /**
     * Gebäudeklasse (Building Class)
     */
    #[ORM\Column(name: 'gklas', length: 4)]
    public string $gklas;

    /**
     * Baujahr des Gebäudes (Construction Year)
     */
    #[ORM\Column(name: 'gbauj', length: 4)]
    public string $gbauj;

    /**
     * Baumonat des Gebäudes (Construction Month)
     */
    #[ORM\Column(name: 'gbaum', length: 2)]
    public string $gbaum;

    /**
     * Bauperiode (Construction Period)
     */
    #[ORM\Column(name: 'gbaup', length: 4)]
    public string $gbaup;

    /**
     * Abbruchjahr des Gebäudes (Demolition Year)
     */
    #[ORM\Column(name: 'gabbj', length: 4)]
    public string $gabbj;

    /**
     * Gebäudefläche (Building Area)
     */
    #[ORM\Column(name: 'garea', length: 5)]
    public string $garea;

    /**
     * Gebäudevolumen (Building Volume)
     */
    #[ORM\Column(name: 'gvol', length: 7)]
    public string $gvol;

    /**
     * Gebäudevolumen: Norm (Building Volume: Standard)
     */
    #[ORM\Column(name: 'gvolnorm', length: 3)]
    public string $gvolnorm;

    /**
     * Informationsquelle zum Gebäudevolumen (Building Volume Information Source)
     */
    #[ORM\Column(name: 'gvolsce', length: 3)]
    public string $gvolsce;

    /**
     * Anzahl Geschosse (Number of Floors)
     */
    #[ORM\Column(name: 'gastw', length: 2)]
    public string $gastw;

    /**
     * Anzahl Wohnungen (Number of Apartments)
     */
    #[ORM\Column(name: 'ganzwhg', length: 3)]
    public string $ganzwhg;

    /**
     * Anzahl separate Wohnräume (Number of Separate Living Rooms)
     */
    #[ORM\Column(name: 'gazzi', length: 3)]
    public string $gazzi;

    /**
     * Zivilschutzraum (Civil Defense Shelter)
     */
    #[ORM\Column(name: 'gschutzr', length: 1)]
    public string $gschutzr;

    /**
     * Energiebezugsfläche (Energy Reference Area)
     */
    #[ORM\Column(name: 'gebf', length: 6)]
    public string $gebf;

    /**
     * Wärmeerzeuger Heizung 1 (Heat Generator Heating 1)
     */
    #[ORM\Column(name: 'gwaerzh1', length: 4)]
    public string $gwaerzh1;

    /**
     * Energie-/Wärmequelle Heizung 1 (Energy/Heat Source Heating 1)
     */
    #[ORM\Column(name: 'genh1', length: 4)]
    public string $genh1;

    /**
     * Informationsquelle Heizung 1 (Information Source Heating 1)
     */
    #[ORM\Column(name: 'gwaersceh1', length: 3)]
    public string $gwaersceh1;

    /**
     * Aktualisierungsdatum Heizung 1 (Update Date Heating 1)
     */
    #[ORM\Column(name: 'gwaerdath1', type: 'date')]
    public \DateTimeImmutable $gwaerdath1;

    /**
     * Wärmeerzeuger Heizung 2 (Heat Generator Heating 2)
     */
    #[ORM\Column(name: 'gwaerzh2', length: 4)]
    public string $gwaerzh2;

    /**
     * Energie-/Wärmequelle Heizung 2 (Energy/Heat Source Heating 2)
     */
    #[ORM\Column(name: 'genh2', length: 4)]
    public string $genh2;

    /**
     * Informationsquelle Heizung 2 (Information Source Heating 2)
     */
    #[ORM\Column(name: 'gwaersceh2', length: 3)]
    public string $gwaersceh2;

    /**
     * Aktualisierungsdatum Heizung 2 (Update Date Heating 2)
     */
    #[ORM\Column(name: 'gwaerdath2', type: 'date')]
    public \DateTimeImmutable $gwaerdath2;

    /**
     * Wärmeerzeuger Warmwasser 1 (Heat Generator Hot Water 1)
     */
    #[ORM\Column(name: 'gwaerzw1', length: 4)]
    public string $gwaerzw1;

    /**
     * Energie-/Wärmequelle Warmwasser 1 (Energy/Heat Source Hot Water 1)
     */
    #[ORM\Column(name: 'genw1', length: 4)]
    public string $genw1;

    /**
     * Informationsquelle Warmwasser 1 (Information Source Hot Water 1)
     */
    #[ORM\Column(name: 'gwaerscew1', length: 3)]
    public string $gwaerscew1;

    /**
     * Aktualisierungsdatum Warmwasser 1 (Update Date Hot Water 1)
     */
    #[ORM\Column(name: 'gwaerdatw1', type: 'date')]
    public \DateTimeImmutable $gwaerdatw1;

    /**
     * Wärmeerzeuger Warmwasser 2 (Heat Generator Hot Water 2)
     */
    #[ORM\Column(name: 'gwaerzw2', length: 4)]
    public string $gwaerzw2;

    /**
     * Energie-/Wärmequelle Warmwasser 2 (Energy/Heat Source Hot Water 2)
     */
    #[ORM\Column(name: 'genw2', length: 4)]
    public string $genw2;

    /**
     * Informationsquelle Warmwasser 2 (Information Source Hot Water 2)
     */
    #[ORM\Column(name: 'gwaerscew2', length: 3)]
    public string $gwaerscew2;

    /**
     * Aktualisierungsdatum Warmwasser 2 (Update Date Hot Water 2)
     */
    #[ORM\Column(name: 'gwaerdatw2', type: 'date')]
    public \DateTimeImmutable $gwaerdatw2;

    /**
     * Datum des Exports (Export Date)
     */
    #[ORM\Column(name: 'gexpdat', type: 'date')]
    public \DateTimeImmutable $gexpdat;
}
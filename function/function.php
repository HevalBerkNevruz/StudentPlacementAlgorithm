<?php

function connectionOpen()
{
    $connection = mysql_connect("localhost", "heval", "120014250") or die(mysql_error());
    $selectDB = mysql_select_db("besyo", $connection) or die(mysql_error());
}

function connectionClose()
{
    if (isset($connection)) {
        mysql_close($connection);
    }
}

function fillArray($query)
{
    $results = array();
    while ($row = mysql_fetch_array($query)) {
        $results[] = $row;
    }
    return $results;
}

function scoreCalculate()
{
    connectionOpen();
    $scoreQuery = mysql_query("select aday_puanlar.aday_ID,aday_puanlar.k1,aday_puanlar.k2,aday_puanlar.gecerli_koordinasyon_derece,aday_puanlar.oysp,adaylar.ad,adaylar.soyad,adaylar.cinsiyet,adaylar.okultur,adaylar.obp,adaylar.ygsmax,
    adaylar.onceki_yerlesme from aday_puanlar join adaylar on aday_puanlar.aday_ID=adaylar.ID");
    connectionClose();
    $scoreSummation = 0;
    $scoreArray = fillArray($scoreQuery);
    $sumOfSquares = 0;
    for ($i = 0; $i < count($scoreArray); $i++) {
        $sumOfSquares += pow($scoreArray[$i]["oysp"], 2);
        $scoreSummation += $scoreArray[$i]["oysp"];
    }
    $standardDeviation = sqrt(($sumOfSquares - ((pow($scoreSummation, 2)) / count($scoreArray))) / (count($scoreArray) - 1));

    for ($i = 0; $i < count($scoreArray); $i++) {
        $standartScore = 10 * (($scoreArray[$i]["oysp"] - ($scoreSummation / count($scoreArray))) / $standardDeviation) + 50;

        if ($scoreArray[$i]["onceki_yerlesme"] == 1) {
            $obp1 = 0.11 / 2;
            $obp2 = 0.3 / 2;
        }
        if ($scoreArray[$i]["okultur"] == 11017) {
            $score = (1.17 * $standartScore) + ($obp1 * $scoreArray[$i]["obp"]) + (0.22 * $scoreArray[$i]["ygsmax"]) + ($obp2 * $scoreArray[$i]["obp"]);
        } else {
            $score = (1.17 * $standartScore) + ($obp1 * $scoreArray[$i]["obp"]) + (0.22 * $scoreArray[$i]["ygsmax"]);
        }
        mysql_query("insert into aday_sonuclar(aday_no,ad,soyad,cinsiyet,ygs,obp,onceki_yerlesme,alan_cikisi,k1,k2,gecerli_koordinasyon,oysp,oysp_kare,
        oysp_toplami,oysp_toplaminin_karesi,oysp_kareler_toplami,ortalama,standartsapma,oyspsp,yerlesme_puani)
        values({$scoreArray[$i][aday_ID]},'{$scoreArray[$i][ad]}','{$scoreArray[$i][soyad]}',
        {$scoreArray[$i][cinsiyet]},{$scoreArray[$i][ygsmax]},{$scoreArray[$i][obp]},{$scoreArray[$i][onceki_yerlesme]},'{$scoreArray[$i][okultur]}',{$scoreArray[$i][k1]},
        {$scoreArray[$i][k2]},{$scoreArray[$i][gecerli_koordinasyon_derece]},{$scoreArray[$i][oysp]},'" . pow($scoreArray[$i][oysp], 2) . "',$scoreSummation,'" . pow($scoreSummation, 2) . "',
        $sumOfSquares,$sumOfSquares/'" . count($scoreArray) . "',$standardDeviation,$standartScore,$score)");
    }
}

function getNationalAthletes($choice)
{
    connectionOpen();
    $query = mysql_query("select adaylar.id,adaylar.tc_No,adaylar.brans_ID,adaylar.aday_no,adaylar.cinsiyet,adaylar.program1,adaylar.program2,
    adaylar.program3,aday_puanlar.yerlestirme_puani from adaylar join aday_puanlar on adaylar.ID=aday_puanlar.aday_ID join aday_sonuclar on adaylar.ID=aday_sonuclar.aday_no
    where (program1 = $choice or program2 = $choice or program3 = $choice) and milli_sporcu=1 and aday_sonuclar.program_ID=NULL order by aday_puanlar.yerlestirme_puani desc");
    connectionClose();
    return fillArray($query);
}

function getCandidateInformation($choice, $limit)
{
    connectionOpen();
    $query = mysql_query("select adaylar.id,adaylar.tc_No,adaylar.brans_ID,adaylar.milli_sporcu,adaylar.aday_no,adaylar.cinsiyet,adaylar.program1,adaylar.program2,adaylar.program3,
    aday_puanlar.yerlestirme_puani from adaylar join aday_puanlar on adaylar.ID=aday_puanlar.aday_ID join aday_sonuclar on adaylar.ID=aday_sonuclar.aday_no
    where (program1 = $choice or program2 = $choice or program3 = $choice) and aday_sonuclar.program_ID=NULL order by aday_puanlar.yerlestirme_puani desc limit $limit");
    connectionClose();
    return fillArray($query);
}

function makeChoice($choice)
{
    $remainingBranchMaleQuota = 0;
    $remainingBranchFemaleQuota = 0;
    connectionOpen();
    $nationalQuota = fillArray(mysql_query("select bay_kontenjan,bayan_kontenjan,baymilli_kontenjan,bayanmilli_kontenjan from programlar where program_ID = $choice"));
    $branchQuota = fillArray(mysql_query("select brans_ID,bay_kontenjan,bayan_kontenjan from program_brans where program_ID = $choice"));
    connectionClose();
    $maleQuota = $nationalQuota[0]["bay_kontenjan"];
    $femaleQuota = $nationalQuota[0]["bayan_kontenjan"];
    $maleNationalQuota = $nationalQuota[0]["baymilli_kontenjan"];
    $femaleNationalQuota = $nationalQuota[0]["bayanmilli_kontenjan"];
    $nationalAthletes = getNationalAthletes($choice);
    connectionOpen();
    for ($i = 0; $i < count($nationalAthletes); $i++) {
        $program = ($nationalAthletes[$i]["program1"] == $choice ? "1" : ($nationalAthletes[$i]["program2"] == $choice ? 2 : ($nationalAthletes[$i]["program3"] == 3 ? 3 : "null")));
        if ($nationalAthletes[$i]["cinsiyet"] == 1 && $maleNationalQuota != 0) {
            mysql_query("insert into aday_sonuclar(milli_durumu,program_ID,tercih,asil_yedek) values(1,$choice,$program,1) where aday_no={$nationalAthletes[$i][id]}");
            $maleNationalQuota--;
        } else if ($nationalAthletes[$i]["cinsiyet"] == 2 && $femaleNationalQuota != 0) {
            mysql_query("insert into aday_sonuclar(milli_durumu,program_ID,tercih,asil_yedek) values(1,$choice,$program,1) where aday_no={$nationalAthletes[$i][id]}");
            $femaleNationalQuota--;
        }
    }

    $totalQuota = $maleQuota + $femaleQuota + $maleNationalQuota + $femaleNationalQuota;
    $reservistQuota = $totalQuota * 2;
    $candidateInformation = getCandidateInformation($choice, $totalQuota * 3);

    for ($i = 0; $i < count($candidateInformation) && $totalQuota != 0; $i++) {
        $program = ($candidateInformation[$i]["program1"] == $choice ? "1" : ($candidateInformation[$i]["program2"] == $choice ? 2 : ($candidateInformation[$i]["program3"] == 3 ? 3 : "null")));
        for ($j = 0; $j <= count($branchQuota); $j++) {
            if ($candidateInformation[$i]["brans_ID"] == $branchQuota[$j]["brans_ID"]) {
                if ($candidateInformation[$i]["cinsiyet"] == 1 && $branchQuota[$j]["bay_kontenjan"] != 0) {
                    mysql_query("insert into aday_sonuclar(milli_durumu,program_ID,tercih,asil_yedek) values({$candidateInformation[$i][milli_sporcu]},$choice,$program,1) where aday_no={$candidateInformation[$i][id]}");
                    $maleQuota--;
                }
                if ($candidateInformation[$i]["cinsiyet"] == 2 && $branchQuota[$j]["bayan_kontenjan"] != 0) {
                    mysql_query("insert into aday_sonuclar(milli_durumu,program_ID,tercih,asil_yedek) values({$candidateInformation[$i][milli_sporcu]},$choice,$program,1) where aday_no={$candidateInformation[$i][id]}");
                    $femaleQuota--;
                }
            }
        }
    }

    $maleQuota += $maleNationalQuota + $remainingBranchMaleQuota;
    $femaleQuota += $femaleNationalQuota + $remainingBranchFemaleQuota;
    $count = count($candidateInformation) * 3;

    for ($i = 0; $i <= $count && $totalQuota != 0; $i++) {
        $program = ($nationalAthletes[$i]["program1"] == $choice ? "1" : ($nationalAthletes[$i]["program2"] == $choice ? 2 : ($nationalAthletes[$i]["program3"] == 3 ? 3 : "null")));
        if ($candidateInformation[$i]["cinsiyet"] == 1 && $maleQuota != 0) {
            mysql_query("insert into aday_sonuclar(milli_durumu,program_ID,tercih,asil_yedek) values({$candidateInformation[$i][milli_sporcu]},$choice,$program,1) where aday_no={$candidateInformation[$i][id]}");
            $maleQuota--;
        } else if ($candidateInformation[$i]["cinsiyet"] == 2 && $femaleQuota != 0) {
            mysql_query("insert into aday_sonuclar(milli_durumu,program_ID,tercih,asil_yedek) values({$candidateInformation[$i][milli_sporcu]},$choice,$program,1) where aday_no={$candidateInformation[$i][id]}");
            $femaleQuota--;
        }
        $totalQuota = $maleQuota + $femaleQuota;
    }

    for ($i = ($reservistQuota / 2); $i < $reservistQuota && $totalQuota == 0; $i++) {
        if ($candidateInformation[$i]["cinsiyet"] == 1) {
            mysql_query("insert into aday_sonuclar(milli_durumu,program_ID,tercih,asil_yedek) values({$candidateInformation[$i][milli_sporcu]},$choice,$program,0) where aday_no={$candidateInformation[$i][id]}");
        } else if ($candidateInformation[$i]["cinsiyet"] == 2) {
            mysql_query("insert into aday_sonuclar(milli_durumu,program_ID,tercih,asil_yedek) values({$candidateInformation[$i][milli_sporcu]},$choice,$program,0) where aday_no={$candidateInformation[$i][id]}");
        }
    }
    connectionClose();
}

?>
# PAL-Tester
Jednoduchá utilitka pro rychlé testování domacích úkolů z předmětu ALG / PAL (ČVUT).

## Požadavky
Pro používání je potřeba mít nainstalovaný PHP interpreter / PHP Apache.

## Testování úloh
Program se spouší příkazem:
`# php index.php programPath testpubDir`

Dále je možné pomocí přepínače `--time` nastavit time-limit úloh (defaultně 3 sekundy  / úloha), nebo pomocí přepínače `--silent` zrušit tisk výstupu vašeho programu do terminálu.

![Terminal sample](terminal.png)

## Použití v prohlížeči
Jelikož je skript napsán v jazyce PHP, je možné jej zkombinovat s PHP Apache a spouštět / zobrazit výsledky v prohlížeči. Cestu k aplikaci, testovacím datům a zvolení časového limitu je pak možné zadat / editovat ve formuláři v hlavičce stránky.

![Website sample](website.png)
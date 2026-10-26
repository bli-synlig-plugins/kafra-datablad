# Kafra Datablad

WordPress-plugin for PDF-datablad knyttet til WooCommerce-produkter. Last opp dokumenter, koble til produkter, og vis dem på produktsider eller i et søkbart dokumentarkiv.

**Versjon:** 1.2.2  
**Forfatter:** Benjamin Pedersen

## Krav

- WordPress
- [WooCommerce](https://woocommerce.com/)

## Installasjon

1. Last ned release-zip fra [Releases](https://github.com/bli-synlig-plugins/kafra-datablad/releases), eller klon dette repoet.
2. Legg mappen `kafra-datablad` i `wp-content/plugins/`.
3. Aktiver **Kafra Datablad** under **Plugins**.

## Admin

- **Datablad** i admin-menyen: opprett datablad, last opp PDF fra mediebiblioteket, og knytt til ett eller flere produkter.
- På **produktredigering** i WooCommerce kan du også knytte datablad direkte.

## Shortcodes

| Shortcode | Beskrivelse |
|-----------|-------------|
| `[kafra_datablad]` / `[kafra_datablader]` | Viser datablad for gjeldende produkt (eller `product_id="123"`, valgfri `title="…"`) |
| `[kafra_dokumenter]` | Søkbart arkiv med paginering (`title="…"`) — typisk på siden `/dokumenter/` |

## Lisens

Ingen lisens er angitt i repoet ennå.

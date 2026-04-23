<?php
/**
 * Multi-strategy content extractor for changelog pages.
 *
 * Strategy 1: Embedded page-data extraction (e.g. Next.js / Featurebase).
 * Strategy 2: Local DOMDocument — XPath selectors + noise stripping.
 * Strategy 3: Jina Reader API — converts any URL to clean markdown.
 * Strategy 4: Raw body text fallback.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AICS_Content_Extractor {

    /**
     * Maximum characters to return to stay within AI token limits.
     */
    const MAX_CONTENT_LENGTH = 30000;

    /**
     * Max version entries to keep per section in a changelog.
     */
    const MAX_VERSIONS_PER_SECTION = 5;

    /**
     * Extract content from a URL using the best available strategy.
     *
     * @param string $html Raw HTML (used for local fallback).
     * @param string $url  Original URL (used for Jina Reader).
     * @return string Extracted content (markdown or HTML).
     */
    public static function extract( $html, $url = '' ) {
        // Strategy 1: Extract structured post data embedded in the page HTML.
        if ( ! empty( $html ) ) {
            $content = self::extract_from_page_data( $html );
            if ( self::is_meaningful( $content ) ) {
                return self::truncate( $content );
            }
        }

        // Strategy 2: Local DOMDocument extraction from the fetched page HTML.
        if ( ! empty( $html ) ) {
            $content = self::extract_from_html( $html );
            if ( self::is_meaningful( $content ) ) {
                return $content;
            }
        }

        // Strategy 3: Jina Reader API — useful when the source page is JS-heavy
        // or the fetched HTML does not contain enough readable changelog content.
        if ( ! empty( $url ) ) {
            $content = self::extract_via_jina( $url );
            if ( self::is_meaningful( $content ) ) {
                $content = self::trim_changelog_sections( $content );
                return self::truncate( $content );
            }
        }

        return '';
    }

    /**
     * Extract changelog entries from embedded page data such as Next.js __NEXT_DATA__.
     *
     * @param string $html Full page HTML.
     * @return string
     */
    private static function extract_from_page_data( $html ) {
        if ( ! preg_match( '/<script id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $matches ) ) {
            return '';
        }

        $json = html_entity_decode( $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $data = json_decode( $json, true );
        if ( ! is_array( $data ) ) {
            return '';
        }

        $entries = [];
        self::collect_changelog_entries( $data, $entries );

        if ( empty( $entries ) ) {
            return '';
        }

        usort( $entries, [ __CLASS__, 'sort_entries_by_date_desc' ] );

        $output = [];
        foreach ( array_slice( $entries, 0, self::MAX_VERSIONS_PER_SECTION ) as $entry ) {
            $title   = trim( wp_strip_all_tags( $entry['title'] ?? '' ) );
            $content = trim( $entry['content'] ?? '' );
            $date    = trim( $entry['date'] ?? '' );

            if ( empty( $title ) || empty( $content ) ) {
                continue;
            }

            if ( ! empty( $date ) ) {
                $output[] = '## ' . $title . ' (' . $date . ')';
            } else {
                $output[] = '## ' . $title;
            }

            $output[] = wp_strip_all_tags( $content );
        }

        return trim( implode( "\n\n", $output ) );
    }

    /**
     * Recursively collect structured changelog entries from embedded JSON data.
     *
     * @param mixed $node Current node.
     * @param array $entries Collected entries.
     * @return void
     */
    private static function collect_changelog_entries( $node, &$entries ) {
        if ( is_array( $node ) ) {
            $is_assoc = array_keys( $node ) !== range( 0, count( $node ) - 1 );

            if ( $is_assoc ) {
                if (
                    isset( $node['type'], $node['title'], $node['content'] ) &&
                    'changelog' === $node['type']
                ) {
                    $entries[] = [
                        'title'   => $node['title'],
                        'content' => $node['content'],
                        'date'    => $node['date'] ?? '',
                    ];
                }

                foreach ( $node as $value ) {
                    self::collect_changelog_entries( $value, $entries );
                }
            } else {
                foreach ( $node as $value ) {
                    self::collect_changelog_entries( $value, $entries );
                }
            }
        }
    }

    /**
     * Sort changelog entries with newest dated items first.
     * Entries without a parseable date are kept after dated entries.
     *
     * @param array $a Entry A.
     * @param array $b Entry B.
     * @return int
     */
    private static function sort_entries_by_date_desc( $a, $b ) {
        $a_time = self::parse_entry_date( $a['date'] ?? '' );
        $b_time = self::parse_entry_date( $b['date'] ?? '' );

        if ( $a_time === $b_time ) {
            return 0;
        }

        if ( false === $a_time ) {
            return 1;
        }

        if ( false === $b_time ) {
            return -1;
        }

        return ( $a_time > $b_time ) ? -1 : 1;
    }

    /**
     * Parse an entry date into a comparable timestamp.
     *
     * @param string $date Raw entry date.
     * @return int|false
     */
    private static function parse_entry_date( $date ) {
        if ( empty( $date ) ) {
            return false;
        }

        $timestamp = strtotime( $date );
        return false === $timestamp ? false : $timestamp;
    }

    /**
     * Fetch clean markdown via Jina Reader API.
     *
     * @param string $url The page URL.
     * @return string Markdown content or empty on failure.
     */
    private static function extract_via_jina( $url ) {
        $jina_url = 'https://r.jina.ai/' . $url;

        $response = wp_remote_get( $jina_url, [
            'timeout'    => 30,
            'headers'    => [
                'Accept'     => 'text/plain',
                'x-no-cache' => 'true',
            ],
            'user-agent' => 'ChangeScout WordPress Plugin',
        ] );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            return '';
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            return '';
        }

        // Jina returns markdown with a metadata header — strip it.
        $body = self::strip_jina_metadata( $body );

        return $body;
    }

    /**
     * Strip Jina Reader metadata header (Title:, URL:, etc.) from response.
     */
    private static function strip_jina_metadata( $text ) {
        // Jina prepends lines like "Title: ...", "URL Source: ...", "Markdown Content:".
        // Find where the actual content starts.
        $lines = explode( "\n", $text );
        $start = 0;

        for ( $i = 0, $len = min( 20, count( $lines ) ); $i < $len; $i++ ) {
            $line = trim( $lines[ $i ] );
            if ( preg_match( '/^(Title|URL Source|Markdown Content)\s*:/i', $line ) ) {
                $start = $i + 1;
            } elseif ( $start > 0 && $line === '' ) {
                $start = $i + 1;
                break;
            }
        }

        return implode( "\n", array_slice( $lines, $start ) );
    }

    /**
     * Local HTML-based extraction (fallback).
     */
    private static function extract_from_html( $html ) {
        $dom = new DOMDocument();
        $dom->loadHTML(
            mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ),
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        $xpath = new DOMXPath( $dom );

        // Strip scripts and common UI noise before trying specific selectors.
        // This avoids returning a broad app wrapper that still contains large
        // client-side payloads such as Next.js page data.
        self::remove_noise( $dom, $xpath );

        // Try changelog-specific selectors.
        $content = self::try_selectors( $dom, $xpath );
        if ( self::is_meaningful( $content ) ) {
            return self::truncate( $content );
        }

        // Strip noise and extract body text.
        $content = self::extract_clean_body( $dom, $xpath );
        if ( self::is_meaningful( $content ) ) {
            return self::truncate( $content );
        }

        // Fall back to raw body text.
        $body = $xpath->query( '//body' );
        if ( $body->length > 0 ) {
            $content = $body->item( 0 )->textContent;
            $content = self::normalize_whitespace( $content );
            return self::truncate( $content );
        }

        return self::truncate( wp_strip_all_tags( $html ) );
    }

    /**
     * Selectors likely to contain changelog content (XPath).
     */
    private static $changelog_selectors = [
        "//*[contains(@class, 'changelog')]",
        "//*[@id='changelog']",
        "//*[contains(@class, 'release-notes')]",
        "//*[contains(@class, 'releases')]",
        "//*[contains(@class, 'versions')]",
        "//*[contains(@class, 'whats-new')]",
        "//*[contains(@class, 'update-log')]",
        "//*[contains(@class, 'entry-content')]",
        "//*[contains(@class, 'wp-block-post-content')]",
        "//*[contains(@class, 'markdown-body')]",
        "//article",
        "//main",
        "//*[@role='main']",
    ];

    private static $noise_tags = [
        'script', 'style', 'nav', 'header', 'footer',
        'noscript', 'iframe', 'svg', 'form',
    ];

    private static $noise_selectors = [
        "//*[contains(@class, 'menu')]",
        "//*[contains(@class, 'nav')]",
        "//*[contains(@class, 'sidebar')]",
        "//*[contains(@class, 'footer')]",
        "//*[contains(@class, 'header')]",
        "//*[contains(@class, 'breadcrumb')]",
        "//*[contains(@class, 'cookie')]",
        "//*[contains(@class, 'popup')]",
        "//*[contains(@class, 'modal')]",
        "//*[contains(@class, 'advertisement')]",
        "//*[contains(@class, 'widget')]",
        "//*[contains(@id, 'sidebar')]",
        "//*[contains(@id, 'footer')]",
        "//*[contains(@id, 'header')]",
        "//*[contains(@id, 'menu')]",
        "//*[contains(@id, 'nav')]",
    ];

    /**
     * Try changelog-specific selectors and return combined HTML.
     */
    private static function try_selectors( $dom, $xpath ) {
        foreach ( self::$changelog_selectors as $selector ) {
            $nodes = $xpath->query( $selector );
            if ( $nodes->length > 0 ) {
                $html = '';
                foreach ( $nodes as $node ) {
                    $html .= $dom->saveHTML( $node );
                }
                return $html;
            }
        }
        return '';
    }

    /**
     * Remove scripts and common UI noise nodes from the DOM.
     */
    private static function remove_noise( $dom, $xpath ) {
        foreach ( self::$noise_tags as $tag ) {
            $elements = $dom->getElementsByTagName( $tag );
            $to_remove = [];
            foreach ( $elements as $el ) {
                $to_remove[] = $el;
            }
            foreach ( $to_remove as $el ) {
                if ( $el->parentNode ) {
                    $el->parentNode->removeChild( $el );
                }
            }
        }

        foreach ( self::$noise_selectors as $selector ) {
            $nodes = $xpath->query( $selector );
            $to_remove = [];
            foreach ( $nodes as $node ) {
                $to_remove[] = $node;
            }
            foreach ( $to_remove as $node ) {
                if ( $node->parentNode ) {
                    $node->parentNode->removeChild( $node );
                }
            }
        }
    }

    /**
     * Return cleaned body content after noise nodes have been removed.
     */
    private static function extract_clean_body( $dom, $xpath ) {
        $body = $xpath->query( '//body' );
        if ( $body->length > 0 ) {
            return $dom->saveHTML( $body->item( 0 ) );
        }

        return '';
    }

    /**
     * Trim long changelogs by keeping only the latest N versions per section.
     *
     * Detects markdown sections (## headings) and version entries within them.
     * This ensures multi-column changelogs (e.g. Free + Pro) both fit within
     * the character limit instead of the Pro section being truncated entirely.
     */
    private static function trim_changelog_sections( $content ) {
        if ( strlen( $content ) <= self::MAX_CONTENT_LENGTH ) {
            return $content;
        }

        $lines    = explode( "\n", $content );
        $sections = [];
        $current  = [ 'header' => '', 'versions' => [], 'preamble' => [] ];
        $in_version = false;
        $version_lines = [];

        foreach ( $lines as $line ) {
            // Detect a major section header (## ProductName, not a version number).
            if ( preg_match( '/^## (?!v?\d)(.+)/i', $line ) ) {
                // Save previous version block if any.
                if ( $in_version && ! empty( $version_lines ) ) {
                    $current['versions'][] = $version_lines;
                    $version_lines = [];
                }
                // Save previous section.
                if ( ! empty( $current['header'] ) || ! empty( $current['versions'] ) || ! empty( $current['preamble'] ) ) {
                    $sections[] = $current;
                }
                $current    = [ 'header' => $line, 'versions' => [], 'preamble' => [] ];
                $in_version = false;
                continue;
            }

            // Detect a version entry (## v1.2.3 or ## 1.2.3).
            if ( preg_match( '/^## v?\d/i', $line ) ) {
                if ( $in_version && ! empty( $version_lines ) ) {
                    $current['versions'][] = $version_lines;
                }
                $version_lines = [ $line ];
                $in_version    = true;
                continue;
            }

            if ( $in_version ) {
                $version_lines[] = $line;
            } else {
                $current['preamble'][] = $line;
            }
        }

        // Flush last version and section.
        if ( $in_version && ! empty( $version_lines ) ) {
            $current['versions'][] = $version_lines;
        }
        $sections[] = $current;

        // If no clear sections with versions detected, return as-is.
        $has_versions = false;
        foreach ( $sections as $s ) {
            if ( count( $s['versions'] ) > self::MAX_VERSIONS_PER_SECTION ) {
                $has_versions = true;
                break;
            }
        }
        if ( ! $has_versions ) {
            return $content;
        }

        // Rebuild with only the latest N versions per section.
        $output = [];
        foreach ( $sections as $s ) {
            if ( ! empty( $s['header'] ) ) {
                $output[] = $s['header'];
            }
            if ( ! empty( $s['preamble'] ) ) {
                $output[] = implode( "\n", $s['preamble'] );
            }
            $kept = array_slice( $s['versions'], 0, self::MAX_VERSIONS_PER_SECTION );
            foreach ( $kept as $v ) {
                $output[] = implode( "\n", $v );
            }
        }

        return implode( "\n", $output );
    }

    private static function is_meaningful( $content ) {
        $text = wp_strip_all_tags( $content );
        $text = self::normalize_whitespace( $text );
        return strlen( $text ) >= 100;
    }

    private static function normalize_whitespace( $text ) {
        return trim( preg_replace( '/\s+/', ' ', $text ) );
    }

    private static function truncate( $content ) {
        if ( strlen( $content ) > self::MAX_CONTENT_LENGTH ) {
            $content = substr( $content, 0, self::MAX_CONTENT_LENGTH );
            // Don't cut in the middle of a markdown heading or HTML tag.
            $last_newline = strrpos( $content, "\n" );
            if ( $last_newline !== false && $last_newline > self::MAX_CONTENT_LENGTH - 500 ) {
                $content = substr( $content, 0, $last_newline );
            } else {
                $last_open = strrpos( $content, '<' );
                $last_close = strrpos( $content, '>' );
                if ( $last_open !== false && ( $last_close === false || $last_open > $last_close ) ) {
                    $content = substr( $content, 0, $last_open );
                }
            }
        }
        return $content;
    }
}

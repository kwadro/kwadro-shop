<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9">
    <xsl:output method="html" encoding="UTF-8" indent="yes"/>

    <xsl:template match="/">
        <html lang="uk">
            <head>
                <meta charset="UTF-8"/>
                <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
                <title>XML Sitemap</title>
                <style>
                    body {
                        font-family: system-ui, sans-serif;
                        margin: 2rem;
                        color: #1f2937;
                        background: #f8faf8;
                    }

                    h1 {
                        margin-bottom: 0.25rem;
                    }

                    p {
                        color: #6b7280;
                        margin-top: 0;
                    }

                    table {
                        width: 100%;
                        border-collapse: collapse;
                        background: #fff;
                        border: 1px solid #d1d5db;
                        border-radius: 8px;
                        overflow: hidden;
                    }

                    th,
                    td {
                        padding: 0.75rem 1rem;
                        text-align: left;
                        border-bottom: 1px solid #e5e7eb;
                        vertical-align: top;
                    }

                    th {
                        background: #eef2ee;
                        font-size: 0.85rem;
                        text-transform: uppercase;
                        letter-spacing: 0.04em;
                    }

                    tr:last-child td {
                        border-bottom: none;
                    }

                    a {
                        color: #166534;
                        word-break: break-all;
                    }
                </style>
            </head>
            <body>
                <h1>XML Sitemap</h1>
                <p>Карта сайту для української версії. Пошукові системи читають XML без стилів.</p>
                <table>
                    <thead>
                        <tr>
                            <th>URL</th>
                            <th>Оновлено</th>
                            <th>Частота</th>
                            <th>Пріоритет</th>
                        </tr>
                    </thead>
                    <tbody>
                        <xsl:for-each select="s:urlset/s:url">
                            <tr>
                                <td>
                                    <a href="{s:loc}"><xsl:value-of select="s:loc"/></a>
                                </td>
                                <td><xsl:value-of select="s:lastmod"/></td>
                                <td><xsl:value-of select="s:changefreq"/></td>
                                <td><xsl:value-of select="s:priority"/></td>
                            </tr>
                        </xsl:for-each>
                    </tbody>
                </table>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>

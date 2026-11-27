# Third-party components

Besides the Composer dependencies listed in `composer.lock`, the following components are distributed with Nebrix:

| Component | Version | License | Location | Source |
|---|---|---|---|---|
| noVNC (core) | 1.7.0 | MPL-2.0 (`public/assets/vendor/novnc/LICENSE.txt`) | `public/assets/vendor/novnc/core` | npm package `@novnc/novnc`, integrity `sha512-ucEJOx4T2avIRCleodk7YobZj5O2Ga2AeLfQ69A/yjG9HHba2+PDgwSkN3FttrmG+70ZGx21sElNFouK13RzyA==` |
| pako (bundled with noVNC) | as shipped with noVNC 1.7.0 | MIT (`public/assets/vendor/novnc/vendor/pako/LICENSE`) | `public/assets/vendor/novnc/vendor/pako` | same package |

The files are unmodified copies of the published package. To update, download the new version from the npm registry, verify its integrity hash against the registry metadata, replace both directories and update this file.

## Composer dependencies with notable licenses

Invoice PDFs are rendered with **dompdf** (LGPL-2.1) and its libraries php-font-lib (LGPL-2.1-or-later), php-svg-lib (LGPL-3.0-or-later), masterminds/html5 (MIT) and sabberworm/php-css-parser (MIT). They are used unmodified as libraries installed by Composer; their license texts ship in `vendor/`. dompdf bundles the DejaVu fonts (Bitstream Vera / DejaVu license), which are embedded into generated PDFs.


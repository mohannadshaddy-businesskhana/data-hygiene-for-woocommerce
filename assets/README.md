# WordPress.org SVN `assets/` folder

This folder maps 1:1 to the `/assets/` folder in the plugin's WP.org SVN repo.
**It is NOT shipped inside the plugin ZIP.** WP.org reads it from SVN separately.

Place the following files here (specs in `STORE_LISTING.md`):

| File                       | Size           | Purpose                              |
|----------------------------|----------------|--------------------------------------|
| `banner-1544x500.png`      | 1544 × 500     | Large banner on the plugin page      |
| `banner-772x250.png`       | 772 × 250      | Small / retina banner                |
| `icon-256x256.png`         | 256 × 256      | Retina icon                          |
| `icon-128x128.png`         | 128 × 128      | Standard icon                        |
| `screenshot-1.png`         | ≤ 1280 wide    | Dashboard with confidence score     |
| `screenshot-2.png`         | ≤ 1280 wide    | Scan results                         |
| `screenshot-3.png`         | ≤ 1280 wide    | Dry-run preview                      |
| `screenshot-4.png`         | ≤ 1280 wide    | Confirmation modal                   |
| `screenshot-5.png`         | ≤ 1280 wide    | Quarantine table                     |
| `screenshot-6.png`         | ≤ 1280 wide    | Audit log                            |

Screenshot order must match the `== Screenshots ==` section of `readme.txt`.

To publish: copy these files into the SVN `assets/` directory (NOT `trunk/`), then `svn add` + `svn ci`.

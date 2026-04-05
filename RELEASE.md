# Release Guide

## First Publish

1. Create a GitHub repository for `kongpda/laravel-attachments-core`
2. Push this directory as the repository root
3. Enable branch protection for `main`
4. Verify GitHub Actions passes
5. Create tag `v0.1.0`

## Updating DPA

Replace the local Composer `path` repository with:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:your-org/laravel-attachments-core.git"
        }
    ],
    "require": {
        "kongpda/laravel-attachments-core": "^0.1"
    }
}
```

Use `dev-main` only during early integration. Prefer tags once the package is stable.

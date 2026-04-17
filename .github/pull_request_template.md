## Description

Briefly describe what this PR does and why.

## Related issue

Closes #<issue-number> (if applicable)

## Type of change

- [ ] Bug fix (non-breaking change that fixes an issue)
- [ ] New feature (non-breaking change that adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to change)
- [ ] Documentation update
- [ ] Code quality / refactor (no functional change)

## Testing performed

Describe what you did to verify the change works:

- [ ] PHPCS passes (`vendor/bin/phpcs --standard=Magento2 .`)
- [ ] Unit tests pass (list affected test files)
- [ ] Manual test on Magento 2.4.x (state which version)
- [ ] Verified generated output on a real catalog (describe scale: product/category/CMS counts)
- [ ] Multi-store / multi-language tested (if applicable)

## Checklist

- [ ] `declare(strict_types=1);` present on any new PHP files
- [ ] No direct `ObjectManager` use
- [ ] Service contracts under `Api/` updated if interfaces changed
- [ ] README / CHANGELOG updated if user-visible behavior changed
- [ ] Commit messages are descriptive

## Screenshots / output samples (if applicable)

Before / after snippets of generated llms.txt or admin UI changes.

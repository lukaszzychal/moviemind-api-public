# Workflow Examples

This directory contains example GitHub Actions workflows that are **not executed** by GitHub Actions.

These are reference implementations that you can copy and modify for your needs.

## Files

- `ci-docker.example.yml` - Docker-based CI workflow example
- `ci-optimized.yml.example` - Optimized CI workflow example

## Usage

To use these examples:

1. Copy the file to `.github/workflows/`
2. Rename it to remove `.example` suffix
3. Adjust the configuration for your needs

## Why They're Here

Files with `.example` suffix in `.github/workflows/` can still be executed by GitHub Actions if they contain valid workflow triggers. To prevent accidental execution, example workflows are stored in this directory instead.


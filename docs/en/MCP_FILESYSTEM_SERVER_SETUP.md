# MCP File System Server Setup

This document describes how to configure the MCP File System Server for use with Cursor or Claude Desktop.

## Overview

The [MCP File System Server](https://github.com/MarcusJellinghaus/mcp_server_filesystem) is a secure Model Context Protocol server that provides file operations for AI assistants. It enables Claude and other assistants to safely read, write, and list files in a designated project directory with robust path validation and security controls.

## Installation

### Prerequisites

- Python 3.8 or higher
- pip (Python package manager)

### Install the Server

```bash
pip install git+https://github.com/MarcusJellinghaus/mcp_server_filesystem.git
```

Or install a specific version:

```bash
pip install git+https://github.com/MarcusJellinghaus/mcp_server_filesystem.git@0.1.1
```

## Configuration

### For Cursor IDE

Create or edit the MCP configuration file. The location depends on your system:

**macOS:**
```bash
~/.cursor/mcp.json
```

**Linux:**
```bash
~/.config/cursor/mcp.json
```

**Windows:**
```bash
%APPDATA%\Cursor\mcp.json
```

### Configuration Options

You have several options for running the MCP server. Choose the one that works best for your setup:

#### Option 1: Using `uvx` (requires `uv` package manager)

First, install `uv`:
```bash
# macOS/Linux
curl -LsSf https://astral.sh/uv/install.sh | sh

# Or with pip
pip install uv
```

Then use this configuration:

```json
{
  "mcpServers": {
    "filesystem": {
      "command": "uvx",
      "args": [
        "mcp-server-filesystem",
        "--project-dir",
        "/Users/lukaszzychal/PhpstormProjects/moviemind-api-public"
      ]
    }
  }
}
```

#### Option 2: Using Python module (recommended if `uvx` is not available)

After installing the package with `pip`, use this configuration:

```json
{
  "mcpServers": {
    "filesystem": {
      "command": "python",
      "args": [
        "-m",
        "mcp_server_filesystem",
        "--project-dir",
        "/Users/lukaszzychal/PhpstormProjects/moviemind-api-public"
      ]
    }
  }
}
```

**Note:** If you use `python3` instead of `python`, replace `"python"` with `"python3"` in the configuration.

#### Option 3: Using full path to Python executable

If you need to specify the exact Python interpreter:

```json
{
  "mcpServers": {
    "filesystem": {
      "command": "/usr/bin/python3",
      "args": [
        "-m",
        "mcp_server_filesystem",
        "--project-dir",
        "/Users/lukaszzychal/PhpstormProjects/moviemind-api-public"
      ]
    }
  }
}
```

**Note:** Replace `/Users/lukaszzychal/PhpstormProjects/moviemind-api-public` with your actual project path in all configurations.

### For Claude Desktop

**macOS:**
```bash
~/Library/Application Support/Claude/claude_desktop_config.json
```

**Windows:**
```bash
%APPDATA%\Claude\claude_desktop_config.json
```

**Linux:**
```bash
~/.config/Claude/claude_desktop_config.json
```

Use the same configuration options as for Cursor IDE (see above). The recommended option is **Option 2: Using Python module** if you don't have `uv` installed:

```json
{
  "mcpServers": {
    "filesystem": {
      "command": "python",
      "args": [
        "-m",
        "mcp_server_filesystem",
        "--project-dir",
        "/Users/lukaszzychal/PhpstormProjects/moviemind-api-public"
      ]
    }
  }
}
```

## Alternative: Using mcp-config Tool

For easier configuration, you can use the `mcp-config` development tool:

### Install mcp-config

```bash
pip install git+https://github.com/MarcusJellinghaus/mcp-config.git
```

### Quick Setup

```bash
# Setup for Claude Desktop with automatic configuration
mcp-config setup mcp-server-filesystem "Filesystem Server" \
  --project-dir /Users/lukaszzychal/PhpstormProjects/moviemind-api-public
```

### Setup with Reference Projects

```bash
mcp-config setup mcp-server-filesystem "Filesystem Server" \
  --project-dir /Users/lukaszzychal/PhpstormProjects/moviemind-api-public \
  --reference-project docs=/path/to/documentation \
  --reference-project examples=/path/to/examples
```

### Setup with Custom Log Configuration

```bash
mcp-config setup mcp-server-filesystem "Filesystem Server" \
  --project-dir /Users/lukaszzychal/PhpstormProjects/moviemind-api-public \
  --log-level DEBUG \
  --log-file /custom/path/server.log
```

## Available Tools

Once configured, the MCP File System Server provides the following tools:

### File Operations

- **`read_file`** - Read file contents
- **`write_file`** - Write or create files
- **`list_directory`** - List files and directories
- **`delete_file`** - Delete files
- **`edit_file`** - Edit files with text replacement
- **`move_file`** - Move or rename files

### Reference Projects

- **`get_reference_projects`** - Discover available reference projects
- **`list_reference_directory`** - List files in reference projects
- **`read_reference_file`** - Read files from reference projects

## Security Features

- All paths are normalized and validated to ensure they remain within the project directory
- Path traversal attacks are prevented
- Files are written atomically to prevent data corruption
- Delete operations are restricted to the project directory
- Reference projects are strictly read-only

## Troubleshooting

### Server Not Starting

1. Verify Python is installed: `python --version` or `python3 --version`
2. Verify the package is installed: `pip list | grep mcp-server-filesystem`
3. Check the configuration file syntax (valid JSON)
4. Check logs for error messages

### Error: "spawn uvx ENOENT" or "uvx not found"

This error means `uvx` is not installed or not in your PATH. **Solution:** Use Option 2 (Python module) instead:

```json
{
  "mcpServers": {
    "filesystem": {
      "command": "python",
      "args": [
        "-m",
        "mcp_server_filesystem",
        "--project-dir",
        "/Users/lukaszzychal/PhpstormProjects/moviemind-api-public"
      ]
    }
  }
}
```

Or install `uv` first:
```bash
# macOS/Linux
curl -LsSf https://astral.sh/uv/install.sh | sh

# Or with pip
pip install uv
```

### Error: "No module named mcp_server_filesystem"

This means the package is not installed. **Solution:** Install it:
```bash
pip install git+https://github.com/MarcusJellinghaus/mcp_server_filesystem.git
```

### Testing the Installation

You can test if the server works by running it manually:

```bash
python -m mcp_server_filesystem --project-dir /Users/lukaszzychal/PhpstormProjects/moviemind-api-public
```

If this works, the MCP configuration should work too.

### Permission Issues

- Ensure the project directory path is correct and accessible
- On macOS/Linux, ensure the path has proper read/write permissions
- Check that the user running Cursor/Claude Desktop has access to the directory

### Path Issues

- Use absolute paths in configuration
- On Windows, use forward slashes or escaped backslashes: `C:\\Users\\...`
- Avoid using `~` or relative paths in configuration

## Example Usage

Once configured, you can use the MCP server in Cursor or Claude Desktop:

```
Read the README.md file
List files in the docs directory
Create a new file test.txt with content "Hello World"
```

The AI assistant will use the MCP File System Server tools to perform these operations safely within your project directory.

## References

- [MCP File System Server GitHub](https://github.com/MarcusJellinghaus/mcp_server_filesystem)
- [MCP Configuration Tool](https://github.com/MarcusJellinghaus/mcp-config)
- [Model Context Protocol Documentation](https://modelcontextprotocol.io)

## License

The MCP File System Server is licensed under the MIT License.


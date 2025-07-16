# Build Agent - WordPress Page Design AI

A powerful WordPress plugin that uses Azure OpenAI to generate WordPress blocks based on user prompts. This AI-powered page design agent helps you quickly create content layouts using natural language descriptions.

## Features

- 🤖 **AI-Powered Block Generation**: Uses Azure OpenAI to generate WordPress Gutenberg blocks
- 🎨 **Natural Language Input**: Describe what you want in plain English
- 🧱 **WordPress Core Blocks**: Generates semantic, responsive layouts using core WordPress blocks
- ⚡ **Real-time Preview**: See generated blocks before inserting them into your page
- 🔧 **Easy Integration**: Works seamlessly with the WordPress block editor
- 🎯 **Smart Insertion**: Automatically inserts blocks into the current page/post
- 📱 **Responsive Design**: Generated layouts work across all device sizes

## Requirements

- WordPress 5.0 or higher (Gutenberg block editor)
- PHP 7.4 or higher
- Azure OpenAI account and API credentials
- Active internet connection

## Installation

1. **Download the Plugin**
   - Download or clone this repository to your WordPress plugins directory
   - The plugin folder should be located at: `/wp-content/plugins/build-agent/`

2. **Activate the Plugin**
   - Go to your WordPress admin panel
   - Navigate to **Plugins > Installed Plugins**
   - Find "Build Agent - WordPress Page Design AI" and click **Activate**

3. **Configure Azure OpenAI**
   - Go to **Settings > Build Agent** in your WordPress admin
   - Enter your Azure OpenAI credentials:
     - **Azure OpenAI Endpoint**: Your Azure resource endpoint (e.g., `https://your-resource.openai.azure.com/`)
     - **API Key**: Your Azure OpenAI API key
     - **Deployment Name**: Your model deployment name (e.g., `gpt-4`, `gpt-35-turbo`)

## Azure OpenAI Setup

1. **Create Azure OpenAI Resource**
   - Go to the [Azure Portal](https://portal.azure.com)
   - Create a new Azure OpenAI resource
   - Note down your endpoint URL and API key

2. **Deploy a Model**
   - In your Azure OpenAI resource, go to "Model deployments"
   - Deploy a GPT-4 or GPT-3.5-Turbo model
   - Note the deployment name you choose

3. **Get API Credentials**
   - Endpoint: Found in your resource overview
   - API Key: Found in "Keys and Endpoint" section
   - Deployment Name: The name you gave your model deployment

## Usage

### Basic Usage

1. **Create or Edit a Page/Post**
   - Go to **Pages > Add New** or **Posts > Add New**
   - Or edit an existing page/post

2. **Find the AI Page Builder**
   - Scroll down to find the "AI Page Builder" meta box
   - It appears below the main content editor

3. **Describe Your Layout**
   - Enter a description of what you want to build, for example:
     - "Create a hero section with a large heading and call-to-action button"
     - "Build a three-column feature grid with icons and descriptions"
     - "Design a testimonial section with customer quotes"

4. **Generate and Insert**
   - Click **Generate Blocks** to create the layout
   - Review the preview of generated blocks
   - Click **Insert into Page** to add them to your content

### Example Prompts

- **Hero Section**: "Create a hero section with a large heading 'Welcome to Our Company', a subtitle describing our services, and a blue call-to-action button saying 'Get Started'"

- **Feature Grid**: "Build a three-column layout showcasing our key features: Fast Performance, 24/7 Support, and Easy Setup. Each column should have a heading and description."

- **About Section**: "Design an about us section with a heading, two paragraphs of text about our company history, and a quote highlighting our mission"

- **Contact Layout**: "Create a contact section with a heading 'Get in Touch', contact information in columns, and a call-to-action"

### Advanced Features

- **Keyboard Shortcuts**: Use `Ctrl+Enter` (or `Cmd+Enter` on Mac) in the prompt textarea to quickly generate blocks
- **Multiple Generations**: Generate different variations by modifying your prompt and clicking generate again
- **Block Editor Integration**: Generated blocks integrate seamlessly with the Gutenberg editor
- **Responsive Design**: All generated layouts are mobile-friendly

## Supported WordPress Blocks

The plugin can generate the following WordPress core blocks:

- **Text Blocks**: Paragraph, Heading, List, Quote
- **Media Blocks**: Image, Gallery, Video, Audio, Embed
- **Design Blocks**: Button, Separator, Spacer, Group
- **Layout Blocks**: Columns, Column, Cover
- **HTML Block**: For custom code

## Troubleshooting

### Common Issues

1. **"Azure OpenAI credentials not configured"**
   - Go to Settings > Build Agent and enter your Azure credentials
   - Make sure the endpoint URL is correct and includes `https://`

2. **"Failed to connect to Azure OpenAI"**
   - Check your internet connection
   - Verify your API key is correct
   - Ensure your Azure OpenAI resource is active

3. **"Failed to parse AI response"**
   - The AI sometimes returns malformed JSON
   - Try rephrasing your prompt to be more specific
   - Check if your deployment model supports the latest features

4. **Blocks not inserting properly**
   - Make sure you're using the block editor (Gutenberg)
   - Try refreshing the page and generating again
   - Check the browser console for JavaScript errors

### Performance Tips

- Use specific, clear prompts for better results
- Break complex layouts into multiple smaller generations
- Review generated blocks before inserting to ensure quality

## Security

- All API communications use HTTPS
- API keys are stored securely in WordPress options
- User inputs are sanitized and validated
- Nonce verification prevents CSRF attacks

## Development

### File Structure

```
build-agent/
├── build-agent.php          # Main plugin file
├── assets/
│   ├── css/
│   │   └── admin.css        # Admin interface styles
│   └── js/
│       └── admin.js         # Admin interface JavaScript
└── README.md               # This file
```

### Hooks and Filters

The plugin provides several hooks for customization:

- `build_agent_system_prompt` - Filter to modify the AI system prompt
- `build_agent_before_generate` - Action before block generation
- `build_agent_after_generate` - Action after successful generation

### Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## Changelog

### Version 1.0.0
- Initial release
- Azure OpenAI integration
- WordPress block generation
- Admin interface
- Block editor integration

## License

This plugin is licensed under the GPL2 license. See the WordPress.org plugin directory for more details.

## Support

For support and bug reports:
- Create an issue on the GitHub repository
- Contact: patrick@patrickthibaudeau.com

## Credits

Developed by Patrick Thibaudeau  
Powered by Azure OpenAI and WordPress

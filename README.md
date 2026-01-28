# Dynamic Tags Lite

**Dynamic Tags Lite** is a lightweight yet powerful WordPress plugin that allows you to inject dynamic content directly into Gutenberg blocks. Easily display Post Meta (Custom Fields) or Post Data (Title, Date, Author, etc.) within your content without writing code.

## 🚀 Features

*   **Dynamic Text Content**: Insert dynamic values into `Paragraph`, `Heading`, and `Button` blocks.
*   **Dynamic Links**:
    *   **Text Links**: Select any text and apply a dynamic URL (e.g., Post Permalink, Author URL, Custom Field URL).
    *   **Image Links**: Wrap `Image` blocks with dynamic links.
*   **Dynamic Media**:
    *   **Images**: Dynamically switch the source of an `Image` block to:
        *   Featured Image
        *   Author Profile Picture
        *   Site Logo
        *   Custom Field Image URL/ID
    *   **Videos**: Dynamically set the `src` for `Video` blocks.
*   **Support for Post Meta**:
    *   Automatically fetches and lists available Meta Keys from your database (up to 500 keys).
    *   Supports manual entry for custom keys.
*   **Support for Post Data**:
    *   ID, Title, Slug, Excerpt, Content, Date, Modified Date.
    *   Author Name, ID, Profile Picture.
    *   Categories, Tags, Comment Count.
*   **Editor Experience**:
    *   **Live Feedback**: Shows `%% key %%` placeholders for text blocks.
    *   **Image Preview**: Allows you to keep a static placeholder image in the editor while rendering dynamically on the frontend.
    *   **Smart Toolbar**: Dedicated "Database" icon in the block toolbar for easy configuration.

## 🛠 Installation

1.  Download the plugin zip file.
2.  Go to your WordPress Dashboard -> **Plugins** -> **Add New** -> **Upload Plugin**.
3.  Upload the zip file and click **Install Now**.
4.  Activate the plugin.

## 📖 Usage

### Adding Dynamic Text
1.  Add or select a **Paragraph** or **Heading** block.
2.  Click the **Database Icon** in the block toolbar.
3.  Select **Source**: "Post Meta" or "Post Data".
4.  Choose the **Field** you want to display.
5.  (Optional) Enter a **Fallback Value**.

### Adding a Dynamic Link to Text
1.  highlight the text you want to link.
2.  Click the **Arrow (More)** in the rich text toolbar (or look for the Link icon).
3.  Select the **Dynamic Link** (chain/database link icon).
4.  Configure the Source and Key for the URL.
5.  Click **Apply**.

### Dynamic Images & Image Links
1.  Select an **Image** block.
2.  Click the **Database Icon** in the toolbar.
3.  **To change the Image Source**: Stay on the "Image Source" tab and select your dynamic field.
4.  **To add a Link to the Image**: Switch to the "Image Link" tab and configure the dynamic URL.

## 💻 Requirements

*   WordPress 5.8+ (Gutenberg Editor)
*   PHP 7.4+

## 🤝 Contributing

Contributions are welcome! Please fork the repository and submit a pull request.

## 📄 License

GPLv2 or later.

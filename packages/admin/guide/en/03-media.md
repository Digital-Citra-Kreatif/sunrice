# Media

**Media** in the sidebar is the library of every file the site uses: images, documents, videos and audio. A file uploaded once can be used in many places, and fields that take an image or a file pick it from here.

## Finding files

- **Folders** are shown above the files. Click a folder to open it; the breadcrumb above returns to **Media (top level)** or a parent folder.
- **Search media** looks through every folder (*Search everywhere…*), by title and file name.
- The type filter shows only **Images**, **Videos**, **Audio** or **Documents** (or **All types**).
- **Trash** shows files moved to the trash; **Back to the list** returns to the library.

## Uploading

Drag files from your computer anywhere onto the page, or click **Upload** and choose them. They go into the folder you're in. Each file uploads on its own, with its progress; a file that isn't allowed (too big, or a type the site doesn't accept) says why. **Clear the finished uploads** tidies the list.

> [!NOTE]
> For safety, the panel refuses scripts, programs and web pages, and SVG images that could run code, whatever their name says. Such an attempt is recorded in the activity log.

If the page says *Files can't be shown: the storage link is missing*, uploads still work but their links lead nowhere until your developer fixes the server. Tell them.

## A file's details

Click a file to open its details:

- A preview, the **Type**, **Size**, **Dimensions** (images) and when it was **Uploaded**.
- **Title** and **Caption**.
- **Alternative text**: what the image shows, for people who can't see it and for search engines. Fill it in for every image that carries meaning.
- On a website in several languages, **In …** (one per other language) takes the alternative text and caption visitors read in that language; empty, they get the ones above.
- **Copy link** copies the file's address; **Open** shows it in a new tab.
- **Replace file** uploads a new version in its place: everything that uses the file shows the new one, and its address stays the same.
- **Used by** lists where the file is used (entries, globals…), with links.

Click **Save** after changing the title, caption or alternative text.

## Organising

- **New folder** creates a folder inside the one you're in. A folder's menu has **Rename** and delete; deleting a folder moves what's in it up a level, so no file is lost.
- Select files (the tick box on each) to **Move to folder** or **Move to trash** several at once.

## The trash and deleting

**Move to trash** takes a file out of the library without deleting it. If the file is used somewhere, the panel asks first and names where: pages using it would show a broken image.

In the **Trash**, **Restore** brings a file back as it was, and **Permanently delete** removes it; this can't be undone. Deleting several files permanently keeps the ones that are still in use; open each to see where it's used.

## Who may do what

Your role decides: seeing the library, uploading, editing details and folders, and the trash are separate permissions. If a button is missing, your role doesn't include it.

> [!TECHNICAL]
> Allowed types, the largest upload and the image sizes made for each picture are set in `config/sunrice-admin.php`. See [Media](docs:panel/media).

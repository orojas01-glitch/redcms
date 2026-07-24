# RED-CMS 5.0 Administrator Manual

## Introduction

RED-CMS is a page and content management system for building structured websites without requiring administrators to edit template code. Version 5.0 brings the main authoring tools into one consistent workspace: create and edit content, organize pages, arrange components visually, manage navigation, choose or build layouts, and recover earlier content versions.

The administrator area is designed around three everyday questions:

1. **What page am I editing?** The page bar shows the current route, its parent pages, its active layout, and a visual layout map.
2. **What belongs on this page?** Edit Content shows every available position, the components assigned to it, hidden content, and direct drag-and-drop arrangement.
3. **What do I want to do next?** Add Content creates a new component, Tools moves existing content, and the lower administration areas manage site structure and shared settings.

Public visitors never see the administrator workspace. Theme presentation and administrator controls are intentionally separated, so changing a website template does not change the editing tools.

RED-CMS 5.0 currently has two maintained profiles: the retained Adriana website and a separate portable `starter-reference` distribution. Their code foundation is shared, but their content databases and release backups must remain separate. See `docs/EDITION-PROFILES.md`.

## First Login

After signing in:

1. Confirm the signed-in account shown at the top. Use **Edit profile** to update your own account details.
2. Use the page breadcrumb or **Navigate pages** to open the page you want to manage.
3. Check the **Layout** name and map before placing content.
4. Open **Edit Content** to edit or arrange existing components.
5. Open **Add Content** to create a component for the current page.
6. Use **Tools → Move Content** when existing content belongs somewhere else.
7. Open Sections, Categories, or Subcategories to change website structure.
8. Open Advanced only for site-wide settings, themes, users, and layout-library work.

Changes that affect public content should be previewed on desktop and mobile before the administrator leaves the task. Version history is available inside supported content editors and should be used instead of manually recreating a previous version.

## Recommended Manual Structure

The complete guide should grow from this introduction in task order:

- Signing in, signing out, and editing a profile
- Understanding pages, Sections, Categories, Subcategories, and Articles
- Navigating the administrator workspace
- Adding and editing each content component
- Uploading and reusing media
- Arranging content and using Hidden Content
- Moving content safely
- Choosing, previewing, and building layouts
- Editing the top navigation
- Restoring content from version history
- Managing administrator users and component permissions
- Managing themes and site-wide Advanced settings
- Troubleshooting, backups, updates, and recovery

## Guided Tour Direction

A future first-login tour should use the real administrator controls rather than a separate demonstration screen. It should be optional, keyboard accessible, dismissible at any step, restartable from Help, and stored per administrator and per tour version.

The first tour should introduce only:

1. Account identity and profile
2. Page navigation and breadcrumb
3. Layout name and map
4. Edit Content and component arrangement
5. Add Content
6. Move Content
7. Structure and Advanced disclosures

The tour must never submit a form, save content, change a layout, or require the user to complete an operational action. Role-aware steps should be omitted when the signed-in account lacks the corresponding permission.

## Current Instructions Page

The existing Instructions page should be updated from this manual after the content is reviewed. Until then, this file is the source draft; it does not modify the live Instructions Article or the primary database.

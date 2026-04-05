# Kongpda Laravel Attachments Core

Backend foundation for the Kongpda attachments ecosystem.

This package owns:

- the `Attachment` model and traits
- storage and path generation contracts
- download and thumbnail routes
- thumbnail generation jobs
- attachment resources and backend configuration
- private R2/S3-friendly file handling

This package does not ship UI.

Use:

- `kongpda/laravel-attachments-core` for backend-only installs
- `kongpda/laravel-attachments-livewire` for Blade/Livewire UI
- `@kongpda/laravel-attachments-react` for React/Inertia UI

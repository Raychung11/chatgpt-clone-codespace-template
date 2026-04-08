Add a new video prompt template to the platform's template library.

Steps:
1. Read `sql/schema.sql` to confirm the `prompt_templates` table structure
2. Generate a high-quality prompt template based on: $ARGUMENTS
3. Insert it directly via a SQL snippet AND also via PHP if the admin panel has a template manager
4. The template should follow this structure:
   - `name`: Short descriptive name (e.g. "Luxury Product Showcase")
   - `category`: One of: Product, Brand Story, Testimonial, Promo, Social Media, Corporate
   - `template`: A detailed, cinematic prompt with {{placeholder}} variables for customisation
     e.g. "{{Brand Name}} product showcase video. A {{product type}} rotates on {{background}}.
          Camera: slow orbit. Lighting: {{lighting style}}. Mood: {{mood}}."
5. Provide both the SQL INSERT and instructions for adding via admin panel

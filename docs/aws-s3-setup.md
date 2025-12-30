# AWS S3 Setup For Backups

## What You Need
- `s3_region`: the AWS region of your bucket (e.g., `ap-southeast-1`)
- `s3_bucket`: your S3 bucket name (must be globally unique)
- `s3_access_key` and `s3_secret`: IAM user credentials with least privilege
- Optional: `s3_path_prefix` such as `backups`

## Create S3 Bucket
- AWS Console → S3 → Buckets → Create bucket
- Pick a unique name and select your region (copy this to `s3_region`)
- Keep “Block all public access” enabled
- Optional: Add a lifecycle rule to expire old backups

## Create IAM User & Keys
- AWS Console → IAM → Users → Add user
- Access type: “Access key - Programmatic access”
- Attach an inline policy restricted to your bucket and `backups/*` prefix:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": ["s3:ListBucket"],
      "Resource": ["arn:aws:s3:::YOUR_BUCKET"],
      "Condition": { "StringLike": { "s3:prefix": ["backups/*"] } }
    },
    {
      "Effect": "Allow",
      "Action": ["s3:PutObject", "s3:GetObject"],
      "Resource": ["arn:aws:s3:::YOUR_BUCKET/backups/*"]
    }
  ]
}
```

- Create the access key; copy both Access key ID and Secret access key immediately (secret shows only once)

## Configure In Admin
- Go to Security → Backup
- Enable auto backup
- Fill:
  - S3 Region: your bucket region
  - S3 Bucket: your bucket name
  - S3 Access Key / Secret: from the IAM user
  - Path Prefix: `backups` (recommended)
  - Leave S3 Endpoint empty for AWS
- Save, then click “Backup now” to test
- Uploaded object key will look like: `backups/database-YYYYMMDD_HHMMSS.sql`

## Scheduler
- Ensure Laravel scheduler runs on the server (via cron):
- `* * * * * docker exec johnrak-admin-backend php artisan schedule:run >> /dev/null 2>&1`

## Notes
- Keep bucket public access blocked
- Use a dedicated IAM user per app and rotate keys periodically
- If cloud isn’t configured, backups are saved locally under `storage/app/backups/`

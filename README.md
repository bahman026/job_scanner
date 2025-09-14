# 🔍 Job Scanner

A powerful Laravel-based web application that aggregates job listings from multiple Iranian job platforms (Jobinja and Jobvision) into a single, easy-to-use interface.

## ✨ Features

- **Multi-Platform Search**: Search across Jobinja and Jobvision simultaneously
- **Real-time Results**: Get live job listings with company information
- **Smart Keyword Matching**: Advanced keyword processing for better results
- **Responsive Design**: Beautiful, mobile-friendly interface built with Tailwind CSS
- **Component Architecture**: Clean, maintainable Blade components
- **Fast Search**: Optimized for quick job discovery
- **Error Handling**: Robust error handling with user-friendly messages
- **Progress Indicators**: Visual feedback during long searches

## 🚀 Quick Start

### Prerequisites

- Docker and Docker Compose
- PHP 8.1+
- Node.js 16+
- Composer

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/bahman026/job_scanner.git
   cd job_scanner
   ```

2. **Start the application with Docker**
   ```bash
   docker-compose up -d
   ```

3. **Install dependencies and build assets**
   ```bash
   docker exec -it job_scanner_app bash -c "composer install"
   docker exec -it job_scanner_app bash -c "npm install && npm run build"
   ```

4. **Access the application**
   - Web Interface: http://localhost:5055
   - API Endpoint: http://localhost:5055/api/search

## 🏗️ Architecture

### System Overview

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Web Browser   │    │   Laravel App   │    │  Job Platforms  │
│                 │    │                 │    │                 │
│  ┌───────────┐  │    │  ┌───────────┐  │    │  ┌───────────┐  │
│  │   UI/UX   │  │◄──►│  │Controller │  │◄──►│  │  Jobinja  │  │
│  │Components │  │    │  │           │  │    │  │           │  │
│  └───────────┘  │    │  └───────────┘  │    │  └───────────┘  │
│                 │    │        │        │    │                 │
│  ┌───────────┐  │    │  ┌───────────┐  │    │  ┌───────────┐  │
│  │JavaScript │  │    │  │  Services │  │    │  │ Jobvision │  │
│  │   Class   │  │    │  │           │  │    │  │           │  │
│  └───────────┘  │    │  └───────────┘  │    │  └───────────┘  │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### Component Structure

```
resources/views/components/
├── layout/
│   ├── app.blade.php          # Main layout
│   ├── header.blade.php       # Header component
│   └── footer.blade.php       # Footer component
├── job-search/
│   ├── form.blade.php         # Search form
│   └── popular-keywords.blade.php # Popular search buttons
├── job-results/
│   ├── section.blade.php      # Results container
│   ├── stats.blade.php        # Statistics dashboard
│   ├── jobinja.blade.php      # Jobinja results
│   └── jobvision.blade.php    # Jobvision results
└── ui/
    ├── button.blade.php       # Reusable button
    └── error-message.blade.php # Error display
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/` | Main job search interface |
| `POST` | `/api/search` | Search for jobs |
| `GET` | `/api/health` | Health check |

### Search API Usage

```bash
curl -X POST http://localhost:5055/api/search \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "keywords=php,laravel,developer"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "jobinja": [...],
    "jobvision": [...]
  },
  "execution_time": 15.2,
  "total_companies": 5,
  "total_jobs": 12,
  "keywords_used": ["php", "laravel", "developer"]
}
```

## 🛠️ Development

### Local Development Setup

1. **Start the development server**
   ```bash
   docker exec -it job_scanner_app bash -c "php artisan serve --host=0.0.0.0 --port=8000"
   ```

2. **Watch for asset changes**
   ```bash
   docker exec -it job_scanner_app bash -c "npm run dev"
   ```

3. **Run tests**
   ```bash
   docker exec -it job_scanner_app bash -c "php artisan test"
   ```

### Code Structure

```
app/
├── Http/Controllers/
│   └── JobSearchController.php    # Main search controller
├── Service/JobScanner/
│   ├── JobScanner.php            # Core job scanning service
│   └── Services/
│       ├── Jobinja.php           # Jobinja integration
│       └── Jobvision.php         # Jobvision integration
└── Console/Commands/
    └── JobScanCommand.php        # CLI command for job scanning

resources/
├── views/
│   ├── components/               # Blade components
│   └── job-search-clean.blade.php # Main view
├── css/app.css                   # Application styles
└── js/job-search.js             # Frontend JavaScript
```

## 🎨 Frontend

### Technologies Used

- **Laravel Blade**: Server-side templating
- **Tailwind CSS**: Utility-first CSS framework
- **Vanilla JavaScript**: Modern ES6+ features
- **Font Awesome**: Icons
- **Vite**: Asset bundling

### Key Features

- **Responsive Design**: Works on all device sizes
- **Loading States**: Visual feedback during searches
- **Error Handling**: User-friendly error messages
- **Progress Indicators**: Animated loading states
- **Popular Keywords**: Quick search suggestions

## 🔧 Configuration

### Environment Variables

Create a `.env` file based on `.env.example`:

```env
APP_NAME="Job Scanner"
APP_ENV=local
APP_KEY=base64:your-app-key
APP_DEBUG=true
APP_URL=http://localhost:5055

# Database (if needed)
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# Cache
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

### Docker Configuration

The application uses Docker Compose with:
- **PHP-FPM**: PHP 8.1 with Laravel
- **Nginx**: Web server
- **PostgreSQL**: Database (optional)
- **Redis**: Caching (optional)

## 📊 Performance

### Search Performance

- **Average Search Time**: 15-30 seconds
- **Timeout**: 60 seconds maximum
- **Concurrent Searches**: Supported
- **Caching**: Optional Redis integration

### Optimization Features

- **Sequential Processing**: Reliable web context execution
- **Error Recovery**: Graceful handling of platform failures
- **Progress Tracking**: Real-time search status
- **Resource Management**: Efficient memory usage

## 🧪 Testing

### Manual Testing

1. **Web Interface Testing**
   - Visit http://localhost:5055
   - Try different keyword combinations
   - Test error scenarios

2. **API Testing**
   ```bash
   # Test basic search
   curl -X POST http://localhost:5055/api/search -d "keywords=php"
   
   # Test health check
   curl http://localhost:5055/api/health
   ```

### Test Cases

- ✅ Single keyword search
- ✅ Multiple keyword search
- ✅ Empty search handling
- ✅ Invalid input handling
- ✅ Network timeout handling
- ✅ Platform failure recovery

## 🚀 Deployment

### Production Deployment

1. **Environment Setup**
   ```bash
   cp .env.example .env
   # Configure production settings
   ```

2. **Build Assets**
   ```bash
   npm run build
   ```

3. **Start Services**
   ```bash
   docker-compose -f docker-compose.prod.yml up -d
   ```

### Docker Commands

```bash
# Start all services
docker-compose up -d

# View logs
docker-compose logs -f

# Stop services
docker-compose down

# Rebuild containers
docker-compose up --build -d
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Guidelines

- Follow PSR-12 coding standards
- Write meaningful commit messages
- Add tests for new features
- Update documentation as needed

## 📝 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## 🙏 Acknowledgments

- **Laravel Framework**: For the robust PHP framework
- **Tailwind CSS**: For the beautiful utility-first CSS
- **Jobinja & Jobvision**: For providing job listing APIs
- **Docker**: For containerization and easy deployment

## 📞 Support

If you encounter any issues or have questions:

1. Check the [Issues](https://github.com/bahman026/job_scanner/issues) page
2. Create a new issue with detailed information
3. Contact the maintainer

---

**Built with ❤️ for job seekers everywhere**

[![GitHub](https://img.shields.io/badge/GitHub-Repository-blue?style=for-the-badge&logo=github)](https://github.com/bahman026/job_scanner)
[![Laravel](https://img.shields.io/badge/Laravel-Framework-red?style=for-the-badge&logo=laravel)](https://laravel.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-CSS-38B2AC?style=for-the-badge&logo=tailwind-css)](https://tailwindcss.com)
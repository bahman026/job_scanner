<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Job Scanner - Find Your Dream Job</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .job-card {
            transition: all 0.3s ease;
        }
        .job-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .loading-spinner {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="gradient-bg text-white py-8">
        <div class="container mx-auto px-4">
            <div class="text-center">
                <h1 class="text-4xl font-bold mb-4">
                    <i class="fas fa-search mr-3"></i>Job Scanner
                </h1>
                <p class="text-xl opacity-90">Find your dream job across multiple platforms</p>
            </div>
        </div>
    </header>

    <!-- Search Form -->
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
                <form id="jobSearchForm" class="space-y-6">
                    @csrf
                    <div>
                        <label for="keywords" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-key mr-2"></i>Search Keywords
                        </label>
                        <input 
                            type="text" 
                            id="keywords" 
                            name="keywords" 
                            placeholder="e.g., php, laravel, developer, remote, javascript, python, react"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            required
                            autocomplete="off"
                        >
                        <div class="mt-2 space-y-1">
                            <p class="text-sm text-gray-500">
                                <i class="fas fa-info-circle mr-1"></i>
                                Separate multiple keywords with commas
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <span class="text-xs text-gray-400">Popular searches:</span>
                                <button type="button" class="text-xs bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded" onclick="setKeyword('php')">php</button>
                                <button type="button" class="text-xs bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded" onclick="setKeyword('developer')">developer</button>
                                <button type="button" class="text-xs bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded" onclick="setKeyword('laravel')">laravel</button>
                                <button type="button" class="text-xs bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded" onclick="setKeyword('javascript')">javascript</button>
                                <button type="button" class="text-xs bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded" onclick="setKeyword('remote')">remote</button>
                                <button type="button" class="text-xs bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded" onclick="setKeyword('python')">python</button>
                                <button type="button" class="text-xs bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded" onclick="setKeyword('react')">react</button>
                            </div>
                            <div class="mt-2 text-xs text-gray-500">
                                <i class="fas fa-lightbulb mr-1"></i>
                                <strong>Tip:</strong> Use specific keywords like "php developer" or "laravel backend" for better results
                            </div>
                        </div>
                    </div>
                    
                    <button 
                        type="submit" 
                        id="searchBtn"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 flex items-center justify-center"
                    >
                        <i class="fas fa-search mr-2"></i>
                        <span id="searchBtnText">Search Jobs</span>
                        <div id="loadingSpinner" class="loading-spinner ml-2 hidden">
                            <i class="fas fa-spinner"></i>
                        </div>
                    </button>
                </form>
            </div>

            <!-- Results Section -->
            <div id="resultsSection" class="hidden">
                <!-- Stats -->
                <div id="statsCard" class="bg-white rounded-lg shadow-lg p-6 mb-6 fade-in">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600" id="totalCompanies">0</div>
                            <div class="text-sm text-gray-600">Companies</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600" id="totalJobs">0</div>
                            <div class="text-sm text-gray-600">Job Openings</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600" id="jobinjaJobs">0</div>
                            <div class="text-sm text-gray-600">Jobinja Jobs</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-orange-600" id="jobvisionJobs">0</div>
                            <div class="text-sm text-gray-600">Jobvision Jobs</div>
                        </div>
                    </div>
                    <div class="mt-4 text-center text-sm text-gray-500">
                        <i class="fas fa-clock mr-1"></i>
                        Search completed in <span id="executionTime">0</span> seconds
                    </div>
                </div>

                <!-- Jobinja Results -->
                <div id="jobinjaResults" class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-briefcase mr-3 text-blue-600"></i>
                        Jobinja Results
                        <span id="jobinjaCount" class="ml-2 bg-blue-100 text-blue-800 text-sm font-medium px-2.5 py-0.5 rounded-full">0</span>
                    </h2>
                    <div id="jobinjaJobsList" class="space-y-4">
                        <!-- Jobinja jobs will be inserted here -->
                    </div>
                </div>

                <!-- Jobvision Results -->
                <div id="jobvisionResults" class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-building mr-3 text-orange-600"></i>
                        Jobvision Results
                        <span id="jobvisionCount" class="ml-2 bg-orange-100 text-orange-800 text-sm font-medium px-2.5 py-0.5 rounded-full">0</span>
                    </h2>
                    <div id="jobvisionJobsList" class="space-y-4">
                        <!-- Jobvision jobs will be inserted here -->
                    </div>
                </div>
            </div>

            <!-- Error Message -->
            <div id="errorMessage" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <span id="errorText"></span>
                </div>
            </div>

        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-6 mt-12">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2024 Job Scanner. Built with Laravel and ❤️</p>
        </div>
    </footer>

    <script>
        document.getElementById('jobSearchForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const searchBtn = document.getElementById('searchBtn');
            const searchBtnText = document.getElementById('searchBtnText');
            const loadingSpinner = document.getElementById('loadingSpinner');
            const resultsSection = document.getElementById('resultsSection');
            const errorMessage = document.getElementById('errorMessage');
            
            // Show loading state
            searchBtn.disabled = true;
            searchBtnText.textContent = 'Searching...';
            loadingSpinner.classList.remove('hidden');
            resultsSection.classList.add('hidden');
            errorMessage.classList.add('hidden');
            
            // Show progress indicator for long searches
            let progressInterval = setInterval(() => {
                const currentText = searchBtnText.textContent;
                if (currentText.includes('...')) {
                    searchBtnText.textContent = 'Searching';
                } else {
                    searchBtnText.textContent = currentText + '.';
                }
            }, 1000);
            
            try {
                // Create AbortController for timeout
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 60000); // 60 second timeout
                
                const response = await fetch('/api/search', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    signal: controller.signal,
                    redirect: 'follow'
                });
                
                clearTimeout(timeoutId);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Non-JSON response:', text.substring(0, 200));
                    throw new Error('Server returned non-JSON response. This might be a server error or timeout.');
                }
                
                const data = await response.json();
                
                
                if (data.success) {
                    displayResults(data.data);
                    updateStats(data);
                    resultsSection.classList.remove('hidden');
                } else {
                    showError(data.message || 'Search failed. Please try again.');
                }
            } catch (error) {
                console.error('Search error:', error);
                
                if (error.name === 'AbortError') {
                    showError('Search timed out after 60 seconds. Please try with more specific keywords or try again later.');
                } else if (error.message.includes('non-JSON response')) {
                    showError('Server error occurred. The search might have timed out or encountered an error. Please try again.');
                } else {
                    showError('An error occurred while searching for jobs: ' + error.message);
                }
            } finally {
                // Clear progress interval
                clearInterval(progressInterval);
                
                // Reset button state
                searchBtn.disabled = false;
                searchBtnText.textContent = 'Search Jobs';
                loadingSpinner.classList.add('hidden');
            }
        });
        
        function displayResults(data) {
            displayJobinjaResults(data.jobinja);
            displayJobvisionResults(data.jobvision);
        }
        
        function displayJobinjaResults(jobs) {
            const container = document.getElementById('jobinjaJobsList');
            const countElement = document.getElementById('jobinjaCount');
            
            countElement.textContent = jobs.length;
            
            if (jobs.length === 0) {
                container.innerHTML = '<div class="text-center text-gray-500 py-8"><i class="fas fa-search text-4xl mb-4"></i><p>No jobs found on Jobinja</p></div>';
                return;
            }
            
            container.innerHTML = jobs.map(company => `
                <div class="job-card bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">
                                <a href="${company.link}" target="_blank" class="hover:text-blue-600">
                                    ${company.link.split('/').pop().replace(/-/g, ' ').toUpperCase()}
                                </a>
                            </h3>
                            <p class="text-sm text-gray-600">
                                <i class="fas fa-map-marker-alt mr-1"></i>
                                ${company.state || 'Location not available'}
                            </p>
                        </div>
                        <span class="bg-blue-100 text-blue-800 text-sm font-medium px-2.5 py-0.5 rounded-full">
                            ${company.jobs.length} job${company.jobs.length !== 1 ? 's' : ''}
                        </span>
                    </div>
                    <div class="space-y-2">
                        ${company.jobs.map(job => `
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <a href="${job}" target="_blank" class="text-blue-600 hover:text-blue-800 font-medium truncate">
                                    ${job.split('/').pop().replace(/%[A-F0-9]{2}/g, ' ').replace(/-/g, ' ')}
                                </a>
                                <a href="${job}" target="_blank" class="text-blue-500 hover:text-blue-700 ml-2">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `).join('');
        }
        
        function displayJobvisionResults(jobs) {
            const container = document.getElementById('jobvisionJobsList');
            const countElement = document.getElementById('jobvisionCount');
            
            countElement.textContent = jobs.length;
            
            if (jobs.length === 0) {
                container.innerHTML = '<div class="text-center text-gray-500 py-8"><i class="fas fa-search text-4xl mb-4"></i><p>No jobs found on Jobvision</p></div>';
                return;
            }
            
            container.innerHTML = jobs.map(company => `
                <div class="job-card bg-white rounded-lg shadow-md p-6 border-l-4 border-orange-500">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">
                                Company ID: ${company.company.companyId || 'N/A'}
                            </h3>
                            <p class="text-sm text-gray-600">
                                <i class="fas fa-map-marker-alt mr-1"></i>
                                ${company.state || 'Location not available'}
                            </p>
                        </div>
                        <span class="bg-orange-100 text-orange-800 text-sm font-medium px-2.5 py-0.5 rounded-full">
                            ${company.jobs.length} job${company.jobs.length !== 1 ? 's' : ''}
                        </span>
                    </div>
                    <div class="space-y-2">
                        ${company.jobs.map(job => `
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex-1">
                                    <a href="${job.link}" target="_blank" class="text-orange-600 hover:text-orange-800 font-medium">
                                        ${job.title || 'Job Title'}
                                    </a>
                                    <p class="text-sm text-gray-500">${job.company || ''}</p>
                                </div>
                                <a href="${job.link}" target="_blank" class="text-orange-500 hover:text-orange-700 ml-2">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `).join('');
        }
        
        function updateStats(data) {
            document.getElementById('totalCompanies').textContent = data.total_companies;
            document.getElementById('totalJobs').textContent = data.total_jobs;
            document.getElementById('executionTime').textContent = data.execution_time;
            
            // Count jobs by platform
            const jobinjaJobs = data.data.jobinja.reduce((sum, company) => sum + company.jobs.length, 0);
            const jobvisionJobs = data.data.jobvision.reduce((sum, company) => sum + company.jobs.length, 0);
            
            document.getElementById('jobinjaJobs').textContent = jobinjaJobs;
            document.getElementById('jobvisionJobs').textContent = jobvisionJobs;
        }
        
        function showError(message) {
            document.getElementById('errorText').textContent = message;
            document.getElementById('errorMessage').classList.remove('hidden');
        }
        
        
        // Function to set keyword from popular searches
        function setKeyword(keyword) {
            const keywordsInput = document.getElementById('keywords');
            const currentValue = keywordsInput.value.trim();
            
            if (currentValue === '') {
                keywordsInput.value = keyword;
            } else {
                // Add keyword if not already present
                const keywords = currentValue.split(',').map(k => k.trim());
                if (!keywords.includes(keyword)) {
                    keywordsInput.value = currentValue + ', ' + keyword;
                }
            }
            
            keywordsInput.focus();
        }
    </script>
</body>
</html>

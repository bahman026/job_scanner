// Job Search Application
class JobSearchApp {
    constructor() {
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        document.getElementById('jobSearchForm').addEventListener('submit', (e) => this.handleSearch(e));
    }

    async handleSearch(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const searchBtn = document.getElementById('searchBtn');
        const searchBtnText = document.getElementById('searchBtnText');
        const loadingSpinner = document.getElementById('loadingSpinner');
        const resultsSection = document.getElementById('resultsSection');
        const errorMessage = document.getElementById('errorMessage');
        
        // Show loading state
        this.setLoadingState(true, searchBtn, searchBtnText, loadingSpinner, resultsSection, errorMessage);
        
        // Show progress indicator for long searches
        const progressInterval = this.startProgressIndicator(searchBtnText);
        
        try {
            const data = await this.performSearch(formData);
            
            if (data.success) {
                console.log('Search successful, hiding error message');
                this.displayResults(data.data);
                this.updateStats(data);
                resultsSection.classList.remove('hidden');
                // Hide error message on successful search
                this.hideError();
            } else {
                console.log('Search failed, showing error message');
                this.showError(data.message || 'Search failed. Please try again.');
            }
        } catch (error) {
            console.error('Search error:', error);
            this.handleSearchError(error);
        } finally {
            this.setLoadingState(false, searchBtn, searchBtnText, loadingSpinner, resultsSection, errorMessage);
            clearInterval(progressInterval);
        }
    }

    setLoadingState(isLoading, searchBtn, searchBtnText, loadingSpinner, resultsSection, errorMessage) {
        searchBtn.disabled = isLoading;
        searchBtnText.textContent = isLoading ? 'Searching...' : 'Search Jobs';
        loadingSpinner.classList.toggle('hidden', !isLoading);
        resultsSection.classList.toggle('hidden', isLoading);
        errorMessage.classList.toggle('hidden', isLoading);
    }

    startProgressIndicator(searchBtnText) {
        return setInterval(() => {
            const currentText = searchBtnText.textContent;
            if (currentText.includes('...')) {
                searchBtnText.textContent = 'Searching';
            } else {
                searchBtnText.textContent = currentText + '.';
            }
        }, 1000);
    }

    async performSearch(formData) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 60000); // 60 second timeout
        
        try {
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
            
            return await response.json();
        } catch (error) {
            clearTimeout(timeoutId);
            throw error;
        }
    }

    displayResults(data) {
        this.displayJobinjaResults(data.jobinja);
        this.displayJobvisionResults(data.jobvision);
    }

    displayJobinjaResults(jobs) {
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

    displayJobvisionResults(jobs) {
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

    updateStats(data) {
        document.getElementById('totalCompanies').textContent = data.total_companies;
        document.getElementById('totalJobs').textContent = data.total_jobs;
        document.getElementById('executionTime').textContent = data.execution_time;
        
        // Count jobs by platform
        const jobinjaJobs = data.data.jobinja.reduce((sum, company) => sum + company.jobs.length, 0);
        const jobvisionJobs = data.data.jobvision.reduce((sum, company) => sum + company.jobs.length, 0);
        
        document.getElementById('jobinjaJobs').textContent = jobinjaJobs;
        document.getElementById('jobvisionJobs').textContent = jobvisionJobs;
    }

    handleSearchError(error) {
        if (error.name === 'AbortError') {
            this.showError('Search timed out after 60 seconds. Please try with more specific keywords or try again later.');
        } else if (error.message.includes('non-JSON response')) {
            this.showError('Server error occurred. The search might have timed out or encountered an error. Please try again.');
        } else {
            this.showError('An error occurred while searching for jobs: ' + error.message);
        }
    }

    showError(message) {
        const errorElement = document.getElementById('errorMessage');
        const errorText = document.getElementById('errorText');
        if (errorElement && errorText) {
            errorText.textContent = message;
            errorElement.classList.remove('hidden');
            errorElement.style.display = 'block';
            console.log('Error message shown:', message);
        } else {
            console.error('Error message elements not found');
        }
    }

    hideError() {
        const errorElement = document.getElementById('errorMessage');
        if (errorElement) {
            errorElement.classList.add('hidden');
            errorElement.style.display = 'none';
            console.log('Error message hidden');
        } else {
            console.error('Error message element not found');
        }
    }
}

// Utility function for setting keywords - make it globally available immediately
window.setKeyword = function(keyword) {
    const keywordsInput = document.getElementById('keywords');
    if (!keywordsInput) {
        console.error('Keywords input not found');
        return;
    }
    
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

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Ensure error message is hidden on page load
    const errorElement = document.getElementById('errorMessage');
    if (errorElement) {
        errorElement.classList.add('hidden');
        errorElement.style.display = 'none';
        console.log('Error message initialized as hidden');
    }
    
    new JobSearchApp();
});

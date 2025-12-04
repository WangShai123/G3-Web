class JCascading {
    constructor(container) {
        this.container = document.querySelector(container);
        this.provinceSelect = this.container.querySelector('[data-selector="province"]');
        this.citySelect = this.container.querySelector('[data-selector="city"]');
        this.districtSelect = this.container.querySelector('[data-selector="district"]');

        this.init();
    }

    init() {
        this.populateProvinceSelect();
        this.provinceSelect.addEventListener('change', this.handleProvinceChange.bind(this));
        this.citySelect.addEventListener('change', this.handleCityChange.bind(this));
    }

    populateProvinceSelect() {
        const provinces = Object.keys(pca);
        provinces.forEach(province => {
            const option = document.createElement('option');
            option.value = province;
            option.textContent = province;
            this.provinceSelect.appendChild(option);
        });
    }

    handleProvinceChange() {
        const selectedProvince = this.provinceSelect.value;
        this.populateCitySelect(selectedProvince);
    }

    populateCitySelect(province) {
        this.clearSelect(this.citySelect);
        this.clearSelect(this.districtSelect);

        if (province) {
            const cities = Object.keys(pca[province]);
            cities.forEach(city => {
                const option = document.createElement('option');
                option.value = city;
                option.textContent = city;
                this.citySelect.appendChild(option);
            });
        }
    }

    handleCityChange() {
        const selectedProvince = this.provinceSelect.value;
        const selectedCity = this.citySelect.value;
        this.populateDistrictSelect(selectedProvince, selectedCity);
    }

    populateDistrictSelect(province, city) {
        this.clearSelect(this.districtSelect);

        if (province && city) {
            const districts = pca[province][city];
            districts.forEach(district => {
                const option = document.createElement('option');
                option.value = district;
                option.textContent = district;
                this.districtSelect.appendChild(option);
            });
        }
    }

    clearSelect(select) {
        select.innerHTML = '';
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = select.id === 'province' ? '省份' : select.id === 'city' ? '城市' : '区县';
        select.appendChild(defaultOption);
    }
}
export default JCascading;
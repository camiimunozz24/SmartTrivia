const mapa = L.map("mapa").setView([-34.6037, -58.3816], 4);
L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 18,
    attribution: "&copy; OpenStreetMap contributors"
}).addTo(mapa);

const campoPais = document.getElementById("pais");
const campoCiudad = document.getElementById("ciudad");
const textoUbicacion = document.getElementById("ubicacion-elegida");
let marcador = null;

mapa.on("click", async (e) => {
    if (marcador) {
        marcador.setLatLng(e.latlng);
    } else {
        marcador = L.marker(e.latlng).addTo(mapa);
    }

    campoPais.value = "";
    campoCiudad.value = "";
    textoUbicacion.textContent = "Buscando ubicación...";

    try {
        const url = "https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=10&accept-language=es"
            + "&lat=" + e.latlng.lat + "&lon=" + e.latlng.lng;
        const respuesta = await fetch(url);
        const datos = await respuesta.json();
        const dir = datos.address || {};

        const pais = dir.country || "";
        const ciudad = dir.city || dir.town || dir.village || dir.municipality || dir.county || dir.state || "";

        if (!pais || !ciudad) {
            textoUbicacion.textContent = "No se pudo detectar un país y ciudad ahí. Probá en otro punto.";
            return;
        }

        campoPais.value = pais;
        campoCiudad.value = ciudad;
        textoUbicacion.textContent = "Ubicación elegida: " + ciudad + ", " + pais;
    } catch (err) {
        textoUbicacion.textContent = "No se pudo consultar la ubicación. Probá de nuevo.";
    }
});

// Validar que haya ubicacion antes de enviar el formulario
document.getElementById("form-registro").addEventListener("submit", (e) => {
    if (!campoPais.value || !campoCiudad.value) {
        e.preventDefault();
        alert("Elegí tu ubicación en el mapa antes de registrarte.");
    }
});
package util;

import com.lowagie.text.Document;
import com.lowagie.text.DocumentException;
import com.lowagie.text.PageSize;
import com.lowagie.text.Paragraph;
import com.lowagie.text.Phrase;
import com.lowagie.text.pdf.PdfPTable;
import com.lowagie.text.pdf.PdfWriter;
import java.io.IOException;
import java.io.OutputStream;
import java.text.NumberFormat;
import java.time.format.DateTimeFormatter;
import java.util.Locale;
import modelo.ReporteIngresoLinea;
import modelo.ReporteResumen;
import modelo.ReporteVentaLinea;

/** Renderiza el reporte administrativo en un PDF descargable. */
public final class ReportePdf {

    private static final Locale LOCALE = Locale.forLanguageTag("es-PE");
    private static final DateTimeFormatter FECHA = DateTimeFormatter.ofPattern(
            "dd/MM/yyyy", LOCALE);
    private static final DateTimeFormatter FECHA_HORA = DateTimeFormatter.ofPattern(
            "dd/MM/yyyy HH:mm", LOCALE);

    private ReportePdf() {
    }

    public static void escribir(ReporteResumen reporte, OutputStream salida)
            throws IOException {
        Document documento = new Document(PageSize.A4.rotate(), 28, 28, 28, 28);
        try {
            PdfWriter.getInstance(documento, salida);
            documento.open();
            documento.add(new Paragraph("DetallesTere · Reporte administrativo"));
            documento.add(new Paragraph(reporte.getEtiquetaPeriodo()));
            documento.add(new Paragraph("Ventas: " + moneda(reporte.getVentasTotales())
                    + " | Ingresos: " + moneda(reporte.getIngresosTotales())
                    + " | Margen estimado: " + moneda(reporte.getMargenEstimado())));
            documento.add(new Paragraph(" "));

            PdfPTable ventas = new PdfPTable(new float[]{1.3f, 3.2f, .8f, 1.2f, 1.2f, 1.2f});
            ventas.setWidthPercentage(100);
            encabezados(ventas, "Fecha", "Artículo", "Cant.", "Venta", "Costo vigente", "Margen");
            for (ReporteVentaLinea linea : reporte.getVentas()) {
                ventas.addCell(texto(linea.getFecha() == null
                        ? "" : FECHA_HORA.format(linea.getFecha())));
                ventas.addCell(texto(linea.getArticulo()));
                ventas.addCell(texto(String.valueOf(linea.getCantidad())));
                ventas.addCell(texto(moneda(linea.getSubtotal())));
                ventas.addCell(texto(moneda(linea.getCostoVigente())));
                ventas.addCell(texto(moneda(linea.getMargen())));
            }
            documento.add(new Paragraph("Detalle de ventas"));
            documento.add(ventas);
            documento.add(new Paragraph(" "));

            PdfPTable ingresos = new PdfPTable(new float[]{1.2f, 2.2f, 3.2f, .8f, 1.2f, 1.2f});
            ingresos.setWidthPercentage(100);
            encabezados(ingresos, "Fecha", "Proveedor", "Artículo", "Cant.", "Costo", "Subtotal");
            for (ReporteIngresoLinea linea : reporte.getIngresos()) {
                ingresos.addCell(texto(FECHA.format(linea.getFecha())));
                ingresos.addCell(texto(linea.getProveedor()));
                ingresos.addCell(texto(linea.getArticulo()));
                ingresos.addCell(texto(String.valueOf(linea.getCantidad())));
                ingresos.addCell(texto(moneda(linea.getPrecioCompra())));
                ingresos.addCell(texto(moneda(linea.getSubtotal())));
            }
            documento.add(new Paragraph("Detalle de ingresos"));
            documento.add(ingresos);
        } catch (DocumentException e) {
            throw new IOException("No se pudo generar el PDF del reporte", e);
        } finally {
            documento.close();
        }
    }

    private static void encabezados(PdfPTable tabla, String... nombres) {
        for (String nombre : nombres) {
            tabla.addCell(new Phrase(nombre));
        }
    }

    private static String texto(String valor) {
        return valor == null ? "" : valor;
    }

    private static String moneda(java.math.BigDecimal valor) {
        return NumberFormat.getCurrencyInstance(LOCALE).format(valor);
    }
}

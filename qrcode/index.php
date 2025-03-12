<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <title>QRCODE /Barcode Generator Tool | Bigleap</title>
</head>

<body class="bg-light">
  <div class="container">
    <main>
      <div class="py-5 text-center">
        <img class="d-block mx-auto mb-4" src="./bigleap.jpg" alt="" width="100">
        <h2>Generate QRCode/ Barcode</h2>
        <p class="lead">Below is a form built entirely for generating QRcode. Each required form group has a validation state that can be triggered by attempting to submit the form without completing it.</p>
      </div>

      <div class="row g-5">
        <div class="col-md-5 col-lg-5 order-md-last">
          <?php include_once('./postform.php'); ?>
        </div>
        <div class="col-md-7 col-lg-7">


          <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="url-tab" data-bs-toggle="tab" data-bs-target="#url" type="button" role="tab" aria-controls="url" aria-selected="true">URL</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="vacrd-tab" data-bs-toggle="tab" data-bs-target="#vacrd" type="button" role="tab" aria-controls="vacrd" aria-selected="false">VCARD</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="barcode-tab" data-bs-toggle="tab" data-bs-target="#barcode" type="button" role="tab" aria-controls="barcode" aria-selected="false">Barcode</button>
            </li>
          </ul>
          <div class="tab-content" id="myTabContent">
            <div class="tab-pane fade show active" id="url" role="tabpanel" aria-labelledby="url-tab">

              <h4 class="mb-2 mt-4">Url / Link</h4>
              <hr class="my-4">

              <form class="needs-validation" novalidate method="post" name="frmurl">
                <div class="row gy-3">
                  <div class="col-md-6">
                    <label for="cc-name" class="form-label">Website</label>
                    <input type="text" class="form-control" name="weburl" id="website" placeholder="http://www.example.com" required>
                    <div class="invalid-feedback">
                      Web link is required
                    </div>
                  </div>
                  <div class="col-12">
                    <div class="justify-content-center d-flex">
                      <a href="./" class="btn btn-secondary me-3"> Reset</a>
                      <button class=" btn btn-primary" type="submit" name="url-post">Generate QRCode</button>
                    </div>
                  </div>
                </div>
              </form>



            </div>
            <div class="tab-pane fade" id="vacrd" role="tabpanel" aria-labelledby="vacrd-tab">

              <h4 class="mt-4 mb-2">VCard Details</h4>
              <hr class="my-4">

              <form class="needs-validation" novalidate method="post" name="frmvcard">
                <div class="row g-3">

                  <div class="col-sm-6">
                    <label for="firstName" class="form-label">First name</label>
                    <input type="text" class="form-control" name="firstname" id="firstName" placeholder="John" value="<?= isset($_POST['firstname']) ? $_POST['firstname'] : '' ?>" required>
                    <div class="invalid-feedback">
                      Valid first name is required.
                    </div>
                  </div>

                  <div class="col-sm-6">
                    <label for="lastName" class="form-label">Last name</label>
                    <input type="text" class="form-control" name="lastname" id="lastName" placeholder="Smith" value="<?= isset($_POST['lastname']) ? $_POST['lastname'] : '' ?>" required>
                    <div class="invalid-feedback">
                      Valid last name is required.
                    </div>
                  </div>

                  <div class="col-sm-6">
                    <label for="phone" class="form-label">Work Phone</label>
                    <input type="text" class="form-control" name="workphone" id="phone" placeholder="055 321 8971" value="<?= isset($_POST['workphone']) ? $_POST['workphone'] : '' ?>" required>
                    <div class="invalid-feedback">
                      Your phone number is required.
                    </div>
                  </div>

                  <div class="col-sm-6">
                    <label for="email" class="form-label">Email <span class="text-muted"></span></label>
                    <input type="email" class="form-control" name="email" id="email" placeholder="you@example.com" value="<?= isset($_POST['email']) ? $_POST['email'] : '' ?>" required>
                    <div class="invalid-feedback">
                      Please enter a valid email address.
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <label for="company" class="form-label">Company</label>
                    <input type="text" class="form-control" name="company" id="company" placeholder="Your Company" value="<?= isset($_POST['company']) ? $_POST['company'] : '' ?>" required>
                    <div class="invalid-feedback">
                      Please enter your company name.
                    </div>
                  </div>

                  <div class="col-sm-6">
                    <label for="website" class="form-label">Website</label>
                    <input type="text" class="form-control" id="website" name="website" placeholder="https://www.yourcompany.ae" value="<?= isset($_POST['website']) ? $_POST['website'] : '' ?>" required>
                    <div class="invalid-feedback">
                      Please enter your website url.
                    </div>
                  </div>

                  <div class="col-sm-6">
                    <label for="role" class="form-label">Designation</label>
                    <input type="text" class="form-control" name="role" id="role" value="<?= isset($_POST['role']) ? $_POST['role'] : '' ?>" placeholder="Designation">
                  </div>

                  <div class="col-sm-6">
                    <label for="address" class="form-label">Address</label>
                    <input type="text" class="form-control" name="address" id="address" value="<?= isset($_POST['address']) ? $_POST['address'] : '' ?>" placeholder="1234 Main St">
                  </div>

                  <div class="col-12">
                    <div class="justify-content-center d-flex">
                      <a href="./" class="btn btn-secondary me-3"> Reset</a>
                      <button class=" btn btn-primary" type="submit" name="vcard-post">Generate QRCode</button>
                    </div>
                  </div>
                </div>
              </form>




            </div>
            <div class="tab-pane fade" id="barcode" role="tabpanel" aria-labelledby="barcode-tab">

              <h4 class="mb-2 mt-4">Barcode</h4>
              <hr class="my-4">

              <form class="needs-validation" novalidate method="post" name="frmbarcode" action="generateBarcode.php">
                <div class="row gy-3">
                  <div class="col-md-6">
                    <label for="cc-name" class="form-label">Barcode Numbers</label>
                    <!-- <input type="text" class="form-control" name="bnumber" id="Number" placeholder="121212" required> -->
                    <textarea class="form-control" rows="10" cols="10" name="bnumber" id="Number" placeholder="121212" required></textarea>
                    <div class="invalid-feedback">
                      Number is required
                    </div>
                    <p class="small text-muted">Please enter multiple value by comma seperated without space eg: 12123,09876 etc..</p>
                  </div>
                  <div class="col-12">
                    <div class="justify-content-center d-flex">
                      <a href="./" class="btn btn-secondary me-3"> Reset</a>
                      <button class=" btn btn-primary" type="submit" name="generate" value="1">Generate Barcode</button>
                    </div>
                  </div>
                </div>
              </form>



            </div>
          </div>

        </div>
      </div>
    </main>

    <footer class="my-5 pt-5 text-muted text-center text-small">
      <p class="mb-1">&copy; 2024 <a href="https://bigleap.ae">Bigleap.ae</a></p>
    </footer>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="./form-validation.js"></script>
  <script type="text/javascript">
    // Using fetch
    async function downloadImage() {

      let qimg = document.getElementById('qr-img');
      let imageSrc = qimg.getAttribute('src');

      const image = await fetch(imageSrc);
      const imageBlog = await image.blob();
      const imageURL = URL.createObjectURL(imageBlog);

      const link = document.createElement('a');
      link.href = imageURL
      link.download = 'qrcode'
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
    }

    async function printQrcode() {
      var divContents = document.getElementById("qrcode-img").innerHTML;
      var a = window.open('', 'YesMachinery', 'height=600, width=600, left=100, top=100');
      a.document.write('<html>');
      a.document.write('<body style="text-align:center;">');
      a.document.write(divContents);
      a.document.write('</body></html>');
      a.document.close();
      a.print();
    }
  </script>
</body>

</html>